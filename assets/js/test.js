const params = new URLSearchParams(window.location.search);
const courseName = params.get('course') || 'รายวิชา';
const subjectId = params.get('subject_id') || '';
const lessonIndex = Math.max(1, Number(params.get('lesson') || 1));

const quizTitle = document.getElementById('quizTitle');
const quizSubtitle = document.getElementById('quizSubtitle');
const quizForm = document.getElementById('quizForm');
const progressText = document.getElementById('progressText');
const progressFill = document.getElementById('progressFill');
const prevBtn = document.getElementById('prevBtn');
const nextBtn = document.getElementById('nextBtn');
const submitBtn = document.getElementById('submitBtn');
const quizActions = document.querySelector('.quiz-actions');
const resultBox = document.getElementById('resultBox');
const backToCourse = document.getElementById('backToCourse');
const quizTimer = document.getElementById('quizTimer');
const QUIZ_PASS_RATIO = 1.0;
const REQUEST_TIMEOUT_MS = 120000;

let quiz = { subtitle: '', questions: [] };
let answers = [];
let currentQuestion = 0;
let lastResultPassed = false;
let quizSubmissionStarted = false;
let quizSessionId = 0;
let quizExpiresAtMs = 0;
let quizTimerInterval = null;
let quizLeaving = false;
let pendingTabSwitch = null;
let draftKey = `quiz-draft:guest:${subjectId}:${lessonIndex}`;

function saveQuizDraft() {
    if (!quiz.questions.length || quizSubmissionStarted) return;
    try {
        localStorage.setItem(draftKey, JSON.stringify({ questions: quiz.questions, answers, currentQuestion, sessionId: quizSessionId }));
    } catch (error) {
        console.warn('[quiz-draft] unable to save draft', error);
    }
}

function shuffleArray(items) {
    for (let index = items.length - 1; index > 0; index -= 1) {
        const swapIndex = Math.floor(Math.random() * (index + 1));
        [items[index], items[swapIndex]] = [items[swapIndex], items[index]];
    }
    return items;
}

function randomizeQuizQuestions(questions) {
    return shuffleArray(questions.map((question) => {
        if (!Array.isArray(question.options) || question.options.length < 2 || Number(question.answer) < 0) return question;
        const originalAnswer = Number(question.answer);
        const shuffledOptions = shuffleArray(question.options.map((text, index) => ({ text, index })));
        return {
            ...question,
            options: shuffledOptions.map((option) => option.text),
            optionIndexes: shuffledOptions.map((option) => option.index),
            answer: originalAnswer
        };
    }));
}

async function requestQuizSession(action = 'start') {
    const response = await fetchJsonWithTimeout('quiz_session.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ action, course_id: subjectId, lesson_index: lessonIndex, session_id: quizSessionId || undefined })
    });
    const result = await response.json();
    if (!response.ok && result.status !== 'already_passed') throw new Error(result.message || `HTTP ${response.status}`);
    return result;
}

function formatRemainingTime(totalSeconds) {
    const seconds = Math.max(0, Math.floor(totalSeconds));
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const remainder = seconds % 60;
    return hours > 0
        ? `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(remainder).padStart(2, '0')}`
        : `${String(minutes).padStart(2, '0')}:${String(remainder).padStart(2, '0')}`;
}

function updateQuizTimer() {
    if (!quizExpiresAtMs || !quizTimer) return;
    const secondsLeft = Math.max(0, Math.ceil((quizExpiresAtMs - Date.now()) / 1000));
    quizTimer.textContent = `เวลาที่เหลือ ${formatRemainingTime(secondsLeft)}`;
    quizTimer.style.color = secondsLeft <= 300 ? '#b91c1c' : '';
    if (secondsLeft === 0 && !quizSubmissionStarted) {
        window.clearInterval(quizTimerInterval);
        window.alert('หมดเวลาทำข้อสอบ ระบบกำลังส่งคำตอบที่บันทึกไว้');
        showResult({ action: 'timeout' });
    }
}

function startQuizTimer() {
    if (!quizExpiresAtMs) return;
    if (quizTimerInterval) window.clearInterval(quizTimerInterval);
    updateQuizTimer();
    quizTimerInterval = window.setInterval(updateQuizTimer, 1000);
}

quizTitle.innerText = `แบบทดสอบวิชา ${courseName} (บทที่ ${lessonIndex})`;
quizSubtitle.innerText = 'กำลังโหลดข้อสอบจากฐานข้อมูล...';
backToCourse.href = subjectId
    ? `index.html?subject_id=${encodeURIComponent(subjectId)}&course=${encodeURIComponent(courseName)}`
    : `index.html?course=${encodeURIComponent(courseName)}`;

async function fetchJsonWithTimeout(url, options = {}, timeoutMs = REQUEST_TIMEOUT_MS) {
    const controller = new AbortController();
    const timer = window.setTimeout(() => controller.abort(), timeoutMs);
    try {
        return await fetch(url, { ...options, signal: controller.signal });
    } finally {
        window.clearTimeout(timer);
    }
}

function isEssayQuestion(item) {
    return String(item?.type || '').toLowerCase() === 'essay';
}

function isScoreableQuestion(item) {
    if (!item) return false;
    if (isEssayQuestion(item)) {
        const answerText = String(item.answer_text || '').trim();
        return answerText !== '' && answerText !== '-';
    }
    return Number(item.answer) >= 0;
}

function renderQuestion() {
    const item = quiz.questions[currentQuestion];
    progressText.innerText = `ข้อ ${currentQuestion + 1} จาก ${quiz.questions.length}`;
    progressFill.style.width = `${((currentQuestion + 1) / quiz.questions.length) * 100}%`;

    if (isEssayQuestion(item)) {
        const savedText = typeof answers[currentQuestion] === 'string' ? answers[currentQuestion] : '';
        quizForm.innerHTML = `
            <h2 class="question-title">${currentQuestion + 1}. ${item.question}</h2>
            <p class="question-score-value">คะแนนเต็มข้อนี้ ${Number(item.max_score || 1).toLocaleString('th-TH', { maximumFractionDigits: 2 })} คะแนน</p>
            <div class="essay-card">
                <label class="essay-label" for="essayAnswerInput">พิมพ์คำตอบของคุณ</label>
                <textarea id="essayAnswerInput" class="essay-input" rows="6" placeholder="พิมพ์คำตอบข้อเขียนที่นี่...">${savedText}</textarea>
                <p class="essay-note">ข้อเขียนจะเก็บคำตอบไว้และนำไปใช้ประกอบการตรวจ</p>
            </div>
        `;
    } else {
        quizForm.innerHTML = `
            <h2 class="question-title">${currentQuestion + 1}. ${item.question}</h2>
            <p class="question-score-value">คะแนนเต็มข้อนี้ ${Number(item.max_score || 1).toLocaleString('th-TH', { maximumFractionDigits: 2 })} คะแนน</p>
            <div class="option-list">
                ${item.options.map((option, index) => `
                    <label class="option-card">
                        <input type="radio" name="answer" value="${item.optionIndexes?.[index] ?? index}" ${answers[currentQuestion] === (item.optionIndexes?.[index] ?? index) ? 'checked' : ''}>
                        <span>${option}</span>
                    </label>
                `).join('')}
            </div>
        `;
    }

    prevBtn.disabled = currentQuestion === 0;
    nextBtn.hidden = currentQuestion === quiz.questions.length - 1;
    submitBtn.hidden = currentQuestion !== quiz.questions.length - 1;
}

function saveCurrentAnswer() {
    const item = quiz.questions[currentQuestion];
    if (isEssayQuestion(item)) {
        const textArea = document.getElementById('essayAnswerInput');
        const value = String(textArea?.value || '').trim();
        answers[currentQuestion] = value || null;
        saveQuizDraft();
        return;
    }

    const selected = quizForm.querySelector('input[name="answer"]:checked');
    answers[currentQuestion] = selected ? Number(selected.value) : null;
    saveQuizDraft();
}

async function saveTestResult(score, totalScore = quiz.questions.length, action = 'submit') {
    const response = await fetchJsonWithTimeout('test_submit.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            course_name: courseName,
            subject_id: subjectId,
            lesson_index: lessonIndex,
            lesson_no: lessonIndex,
            action,
            session_id: quizSessionId,
            score,
            total_score: totalScore,
            answers: Object.fromEntries(quiz.questions.map((question, index) => [String(question.question_id), answers[index]]))
        })
    });
    return response.json();
}

function renderAnswerKey(resultAnswers = [], attemptId = 0) {
    return `
        <div class="answer-key">
            <h3>ผลตรวจและเฉลยรายข้อ</h3>
            ${resultAnswers.map((item, index) => {
                if (item.is_correct === null) return `<div class="answer-item"><b>ข้อ ${index + 1}: ${escapeHtml(item.question)}</b><p>คำตอบข้อเขียน: ${escapeHtml(item.answer_text || 'ไม่ได้ตอบ')} · รออาจารย์ตรวจ</p></div>`;
                const isCorrect = Boolean(item.is_correct);
                const wasAnswered = Boolean(item.selected_choice);
                const teacherExplanation = String(item.teacher_explanation || '').trim();
                const aiExplanation = String(item.ai_explanation || '').trim();
                const explanation = isCorrect
                    ? `<div class="teacher-explanation" style="margin-top:10px;padding:12px;border-radius:8px;background:#f8fafc;border:1px solid #dbe3ef"><strong>คำอธิบายจากอาจารย์</strong><p style="white-space:pre-wrap">${escapeHtml(teacherExplanation || 'อาจารย์กำหนดเฉลยข้อนี้ไว้')}</p></div>`
                    : wasAnswered ? `<div class="teacher-explanation" style="margin-top:10px;padding:12px;border-radius:8px;background:#f8fafc;border:1px solid #dbe3ef"><strong>คำอธิบายจากอาจารย์</strong><p style="white-space:pre-wrap">${escapeHtml(teacherExplanation || 'อาจารย์ยังไม่ได้เพิ่มคำอธิบาย')}</p></div>` : '';
                const tutor = attemptId && isCorrect ? `<div class="question-tutor" data-tutor-question="${Number(item.question_id)}" style="margin-top:12px;padding:10px;border-radius:8px;background:#f8fafc"><strong>ยังไม่เข้าใจข้อนี้? ถาม AI ได้</strong><div class="tutor-messages" aria-live="polite" style="font-size:13px;white-space:pre-wrap;margin:6px 0"></div><form class="tutor-form" style="display:flex;gap:6px"><input class="tutor-input" type="text" maxlength="700" placeholder="พิมพ์คำถามสั้นๆ..." required style="flex:1;min-width:0"><button type="submit" class="primary-btn" style="padding:7px 12px">ถาม</button></form></div>` : '';
                const aiBlock = aiExplanation ? `<div class="ai-explanation" style="margin-top:10px;padding:12px;border-radius:8px;background:#eff6ff;border:1px solid #bfdbfe"><strong>คำอธิบายและวิธีทำเพิ่มเติมจาก AI</strong><p style="white-space:pre-wrap">${escapeHtml(aiExplanation)}</p></div>` : '';
                return `<div class="answer-item ${isCorrect ? 'is-correct' : 'is-wrong'}"><b>ข้อ ${index + 1}: ${escapeHtml(item.question)}</b><p>${wasAnswered ? (isCorrect ? '✓ ตอบถูก' : '✗ ตอบผิด') : 'ไม่ได้ตอบ'}</p><p>คำตอบของคุณ: ${escapeHtml(item.selected_choice ? `${item.selected_choice}. ${item.selected_text}` : 'ไม่ได้ตอบ')}</p><p>คำตอบที่ถูก: ${escapeHtml(item.correct_choice)}. ${escapeHtml(item.correct_text)}</p>${explanation}${aiBlock}${tutor}</div>`;
            }).join('')}
        </div>
    `;
}

const questionTutorHistory = new Map();
resultBox.addEventListener('submit', async (event) => {
    const form = event.target.closest('.tutor-form');
    if (!form) return;
    event.preventDefault();
    const panel = form.closest('[data-tutor-question]');
    const input = panel.querySelector('.tutor-input');
    const button = form.querySelector('button');
    const questionId = panel.dataset.tutorQuestion;
    const question = input.value.trim();
    if (!question) return;
    const history = questionTutorHistory.get(questionId) || [];
    history.push({ role: 'user', content: question });
    input.value = '';
    input.disabled = true;
    button.disabled = true;
    const messages = panel.querySelector('.tutor-messages');
    messages.textContent = 'กำลังคิดคำอธิบาย...';
    try {
        const response = await fetch('student_question_tutor_api.php', {
            method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ attempt_id: Number(window.__quizAttemptId), question_id: Number(questionId), history })
        });
        const data = await response.json();
        if (!response.ok || !data.ok) throw new Error(data.message || 'ถาม AI ไม่สำเร็จ');
        history.push({ role: 'assistant', content: data.answer });
        questionTutorHistory.set(questionId, history.slice(-8));
        messages.textContent = history.map(item => `${item.role === 'user' ? 'คุณ' : 'ติวเตอร์'}: ${item.content}`).join('\n\n');
    } catch (error) {
        history.pop();
        messages.textContent = error.message || 'ถาม AI ไม่สำเร็จ';
    } finally {
        input.disabled = false;
        button.disabled = false;
        input.focus();
    }
});

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char]);
}

function renderResultActionButton(isPassed, isPendingReview = false) {
    if (isPassed || isPendingReview) return '';
    return `
        <div class="result-action-wrap">
            <button type="button" id="resultActionBtn" class="primary-btn">ทำแบบทดสอบใหม่</button>
        </div>
    `;
}

function bindResultAction(isPassed) {
    const actionBtn = document.getElementById('resultActionBtn');
    if (!actionBtn) return;
    actionBtn.onclick = () => {
        window.location.reload();
    };
}

async function showResult(options = {}) {
    if (quizSubmissionStarted) return;
    quizSubmissionStarted = true;
    if (quizTimerInterval) window.clearInterval(quizTimerInterval);
    saveCurrentAnswer();
    const totalScore = quiz.questions.reduce((total, item) => total + Number(item.max_score || 1), 0);
    const essayCount = quiz.questions.filter((item) => isEssayQuestion(item)).length;
    const hasPendingEssay = quiz.questions.some((item, index) => isEssayQuestion(item) && String(answers[index] || '').trim() !== '');
    const score = 0;
    const requiredScore = totalScore * QUIZ_PASS_RATIO;
    const isPassed = false;
    const effectivePassed = false;

    resultBox.hidden = false;
    resultBox.innerHTML = `
        <h2>ผลคะแนน</h2>
        <p>คุณได้ <span class="score">${score}/${totalScore}</span> คะแนน</p>
        <p id="quizResultMessage">${options.action === 'timeout' ? 'หมดเวลาแล้ว ระบบส่งคำตอบที่บันทึกไว้' : hasPendingEssay ? 'ส่งข้อเขียนแล้ว รออาจารย์ตรวจผลก่อน' : 'กำลังตรวจคำตอบจากระบบ...'}</p>
        <p>เกณฑ์ผ่าน: ${requiredScore} คะแนน</p>
        ${essayCount > 0 ? `<p>มีข้อเขียน ${essayCount} ข้อ รอครูตรวจ</p>` : ''}
        <p id="saveStatus">กำลังบันทึกผลแบบทดสอบ...</p>
        ${renderResultActionButton(effectivePassed, hasPendingEssay)}
    `;
    bindResultAction(effectivePassed);

    quizForm.hidden = true;
    prevBtn.hidden = true;
    nextBtn.hidden = true;
    submitBtn.hidden = true;
    if (quizActions) quizActions.hidden = true;

    const saveStatus = document.getElementById('saveStatus');
    window.__lastQuizSaveResult = { ok: false, status: 'pending', score, totalScore };
    try {
        const result = await saveTestResult(score, totalScore, options.action || 'submit');
        if (result.status === 'unauthorized') {
            window.__lastQuizSaveResult = { ok: false, status: 'unauthorized', error: result.message || 'unauthorized', score, totalScore };
            saveStatus.innerText = '';
            console.warn('[quiz-save] unauthorized', result.message || '');
            return;
        }
        if (result.status === 'success') {
            localStorage.removeItem(draftKey);
            const authoritativeScore = Number(result.score || 0);
            const scoreNode = resultBox.querySelector('.score');
            if (scoreNode) scoreNode.textContent = `${authoritativeScore}/${result.total_score}`;
            lastResultPassed = result.quiz_status === 'passed';
            window.__lastQuizSaveResult = {
                ok: true,
                status: result.quiz_status || 'success',
                score: authoritativeScore,
                totalScore,
                requiredScore,
            };
            saveStatus.innerText = 'บันทึกผลแล้ว';
            const resultMessage = document.getElementById('quizResultMessage');
            if (resultMessage) {
                resultMessage.innerText = lastResultPassed
                    ? 'ผ่านเกณฑ์แล้ว สามารถกลับไปหน้ารายวิชาได้'
                    : result.pending_review
                        ? 'ส่งข้อเขียนแล้ว รออาจารย์ตรวจผลก่อน'
                        : result.quiz_status === 'failed'
                            ? 'ไม่ผ่านเกณฑ์ กรุณาทำแบบทดสอบใหม่'
                            : resultMessage.innerText;
            }
            if (Array.isArray(result.answers) && result.answers.length && !result.pending_review) {
                const answerKey = document.createElement('div');
                window.__quizAttemptId = Number(result.attempt_id || 0);
                answerKey.innerHTML = renderAnswerKey(result.answers, result.quiz_status === 'passed' ? result.attempt_id : 0);
                saveStatus.before(answerKey);
            }
            if (Number.isFinite(Number(result.best_score))) {
                const bestScore = document.createElement('p');
                bestScore.textContent = `คะแนนสูงสุดของคุณ: ${result.best_score}/${result.total_score}`;
                saveStatus.before(bestScore);
            }
            if (result.pending_review) {
                const pendingNotice = document.createElement('p');
                pendingNotice.textContent = 'ส่งคำตอบข้อเขียนแล้ว รออาจารย์ตรวจและยืนยันผล';
                pendingNotice.style.color = '#b45309';
                pendingNotice.style.fontWeight = '600';
                saveStatus.before(pendingNotice);
            }
            const totalNode = resultBox.querySelector('p:nth-of-type(3)');
            if (totalNode) totalNode.textContent = `เกณฑ์ผ่าน: ${result.required_score} คะแนน`;
            const actionWrap = resultBox.querySelector('.result-action-wrap');
            const updatedAction = renderResultActionButton(lastResultPassed, Boolean(result.pending_review));
            if (actionWrap) {
                if (updatedAction) actionWrap.outerHTML = updatedAction;
                else actionWrap.remove();
                bindResultAction(lastResultPassed);
            } else if (updatedAction) {
                resultBox.insertAdjacentHTML('beforeend', updatedAction);
                bindResultAction(lastResultPassed);
            }
        } else if (result.status === 'already_passed') {
            localStorage.removeItem(draftKey);
            saveStatus.innerText = result.message || 'คุณผ่านแบบทดสอบบทนี้แล้ว ไม่สามารถทำซ้ำได้';
            resultBox.querySelector('.result-action-wrap')?.remove();
        } else {
            window.__lastQuizSaveResult = {
                ok: false,
                status: result.status || 'error',
                error: result.message || 'save_failed',
                score: authoritativeScore,
                totalScore,
            };
            saveStatus.innerText = '';
            console.warn('[quiz-save] failed', window.__lastQuizSaveResult);
        }
    } catch (error) {
        window.__lastQuizSaveResult = {
            ok: false,
            status: 'exception',
            error: error?.message || String(error),
            score,
            totalScore,
        };
        saveStatus.innerText = '';
        console.error('[quiz-save] exception', error);
    }
}

async function loadQuizFromDatabase() {
    if (!subjectId) {
        quizSubtitle.innerText = 'ไม่พบ subject_id';
        quizForm.innerHTML = '<p>ไม่สามารถโหลดข้อสอบได้ กรุณากลับไปเลือกวิชาใหม่</p>';
        prevBtn.hidden = true;
        nextBtn.hidden = true;
        submitBtn.hidden = true;
        return;
    }

    try {
        const response = await fetchJsonWithTimeout(`api_quiz.php?action=get_questions&subject_id=${encodeURIComponent(subjectId)}&lesson=${lessonIndex}`, {
            credentials: 'same-origin'
        });
        const result = await response.json();

        if (result.status === 'already_passed') {
            quizSubtitle.innerText = 'คุณผ่านแบบทดสอบบทนี้แล้ว';
            quizForm.innerHTML = '<p>ผ่านแบบทดสอบบทนี้แล้ว จึงไม่สามารถทำซ้ำได้</p>';
            prevBtn.hidden = true;
            nextBtn.hidden = true;
            submitBtn.hidden = true;
            if (quizActions) quizActions.hidden = true;
            return;
        }

        if (result.status !== 'success' || !Array.isArray(result.questions) || result.questions.length === 0) {
            quizSubtitle.innerText = 'ยังไม่มีข้อสอบของบทนี้ในฐานข้อมูล';
            quizForm.innerHTML = '<p>ยังไม่มีข้อสอบ กรุณาติดต่อผู้สอนเพื่อเพิ่มคำถาม</p>';
            prevBtn.hidden = true;
            nextBtn.hidden = true;
            submitBtn.hidden = true;
            return;
        }

        const session = await requestQuizSession('start');
        if (session.status === 'already_passed') {
            quizSubtitle.innerText = 'คุณผ่านแบบทดสอบบทนี้แล้ว';
            quizForm.innerHTML = '<p>ผ่านแบบทดสอบบทนี้แล้ว จึงไม่สามารถทำซ้ำได้</p>';
            prevBtn.hidden = true;
            nextBtn.hidden = true;
            submitBtn.hidden = true;
            if (quizActions) quizActions.hidden = true;
            return;
        }
        if (session.status !== 'success') throw new Error(session.message || 'เริ่มเซสชันข้อสอบไม่สำเร็จ');
        quizSessionId = Number(session.session_id);
        quizExpiresAtMs = Number(session.expires_at_ms);
        if (session.previous_timed_out) window.alert('เวลาสอบครั้งก่อนหมดแล้ว ระบบบันทึกผลเป็นไม่ผ่าน คุณสามารถเริ่มทำใหม่ได้');

        draftKey = `quiz-draft:${result.draft_scope || 'guest'}:${subjectId}:${lessonIndex}`;
        let draft = null;
        try { draft = JSON.parse(localStorage.getItem(draftKey) || 'null'); } catch (_) {}
        const serverQuestionIds = result.questions.map((question) => Number(question.question_id)).sort((a, b) => a - b);
        const draftQuestionIds = Array.isArray(draft?.questions) ? draft.questions.map((question) => Number(question.question_id)).sort((a, b) => a - b) : [];
        const validDraft = serverQuestionIds.length === draftQuestionIds.length
            && serverQuestionIds.every((id, index) => id === draftQuestionIds[index])
            && Array.isArray(draft.answers) && draft.answers.length === result.questions.length
            && Number(draft.sessionId) === quizSessionId;
        quiz = {
            subtitle: `ทำแบบทดสอบบทที่ ${lessonIndex}`,
            questions: validDraft ? draft.questions : randomizeQuizQuestions(result.questions)
        };
        answers = validDraft ? draft.answers : new Array(quiz.questions.length).fill(null);
        currentQuestion = validDraft ? Math.max(0, Math.min(quiz.questions.length - 1, Number(draft.currentQuestion) || 0)) : 0;
        quizSubtitle.innerText = quiz.subtitle;
        renderQuestion();
        saveQuizDraft();
        startQuizTimer();
    } catch (error) {
        quizSubtitle.innerText = 'โหลดข้อสอบไม่สำเร็จ';
        quizForm.innerHTML = '<p>ไม่สามารถเชื่อมต่อฐานข้อมูลข้อสอบได้ หรือใช้เวลานานเกินไป</p><button type="button" class="primary-btn" id="retryQuizBtn" style="margin-top:12px;">ลองโหลดใหม่</button>';
        prevBtn.hidden = true;
        nextBtn.hidden = true;
        submitBtn.hidden = true;
        const retryBtn = document.getElementById('retryQuizBtn');
        if (retryBtn) retryBtn.onclick = () => window.location.reload();
    }
}

nextBtn.addEventListener('click', () => {
    saveCurrentAnswer();
    const item = quiz.questions[currentQuestion];
    if (isEssayQuestion(item)) {
        if (!String(answers[currentQuestion] || '').trim()) {
            alert('กรุณาพิมพ์คำตอบก่อน');
            return;
        }
    } else if (answers[currentQuestion] === null) {
        alert('กรุณาเลือกคำตอบก่อน');
        return;
    }
    currentQuestion += 1;
    renderQuestion();
    saveQuizDraft();
});

prevBtn.addEventListener('click', () => {
    saveCurrentAnswer();
    currentQuestion -= 1;
    renderQuestion();
    saveQuizDraft();
});

submitBtn.addEventListener('click', () => {
    if (!quizForm.hidden) {

        saveCurrentAnswer();
        const item = quiz.questions[currentQuestion];
        if (isEssayQuestion(item)) {
            if (!String(answers[currentQuestion] || '').trim()) {
                alert('กรุณาพิมพ์คำตอบก่อน');
                return;
            }
        } else if (quizForm.querySelector('input[name="answer"]:checked') === null) {
            alert('กรุณาเลือกคำตอบก่อน');
            return;
        }
        showResult();
        return;
    }

    window.location.href = backToCourse.href;
});

loadQuizFromDatabase();
quizForm.addEventListener('input', saveCurrentAnswer);
quizForm.addEventListener('change', saveCurrentAnswer);

backToCourse.addEventListener('click', async (event) => {
    if (!quizSessionId || quizSubmissionStarted || quizLeaving) return;
    event.preventDefault();
    const shouldExit = window.confirm('ยืนยันออกจากแบบทดสอบหรือไม่? หากออกตอนนี้ ระบบจะบันทึกผลเป็นไม่ผ่าน และต้องเริ่มทำข้อสอบใหม่');
    if (!shouldExit) return;
    saveCurrentAnswer();
    quizLeaving = true;
    if (quizTimerInterval) window.clearInterval(quizTimerInterval);
    try {
        const result = await requestQuizSession('abandon');
        if (result.status !== 'success') throw new Error(result.message || 'บันทึกผลไม่ผ่านไม่สำเร็จ');
        localStorage.removeItem(draftKey);
        window.location.assign(backToCourse.href);
    } catch (error) {
        quizLeaving = false;
        alert(error.message || 'บันทึกผลสอบไม่สำเร็จ กรุณาลองอีกครั้ง');
        startQuizTimer();
    }
});

window.addEventListener('beforeunload', (event) => {
    if (!quizSessionId || quizSubmissionStarted || quizLeaving) return;
    event.preventDefault();
    event.returnValue = '';
});

window.addEventListener('pagehide', () => {
    if (!quizSessionId || quizSubmissionStarted || quizLeaving) return;
    const body = new Blob([JSON.stringify({
        action: 'abandon',
        course_id: subjectId,
        lesson_index: lessonIndex,
        session_id: quizSessionId
    })], { type: 'application/json' });
    navigator.sendBeacon('quiz_session.php', body);
});

function handlePendingTabSwitch() {
    if (!pendingTabSwitch || document.visibilityState !== 'visible') return;
    const event = pendingTabSwitch;
    pendingTabSwitch = null;
    if (event.auto_failed) {
        quizLeaving = true;
        quizSubmissionStarted = true;
        if (quizTimerInterval) window.clearInterval(quizTimerInterval);
        localStorage.removeItem(draftKey);
        alert('คุณสลับแท็บหลังได้รับคำเตือนครบ 3 ครั้ง ระบบบันทึกผลเป็นไม่ผ่านและกำลังกลับไปหน้ารายวิชา');
        window.location.assign(backToCourse.href);
        return;
    }
    const warning = event.tab_switch_count >= 3
        ? 'คุณได้รับคำเตือนครบ 3 ครั้งแล้ว หากสลับอีกครั้งระบบจะบันทึกผลเป็นไม่ผ่าน'
        : 'หากสลับครบ 3 ครั้งแล้วสลับอีก ระบบจะบันทึกผลเป็นไม่ผ่าน';
    alert(`เตือน: คุณสลับออกจากหน้าข้อสอบครั้งที่ ${event.tab_switch_count} จาก 3 ครั้ง ${warning}`);
}

document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'hidden') {
        if (!quizSessionId || quizSubmissionStarted || quizLeaving) return;
        requestQuizSession('tab_switch').then((result) => {
            if (result.status === 'success') {
                pendingTabSwitch = result;
                handlePendingTabSwitch();
            }
        }).catch((error) => console.warn('[quiz-session] unable to record tab switch', error));
        return;
    }
    handlePendingTabSwitch();
});
