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
const QUIZ_PASS_RATIO = 1.0;
const REQUEST_TIMEOUT_MS = 8000;

let quiz = { subtitle: '', questions: [] };
let answers = [];
let currentQuestion = 0;
let lastResultPassed = false;
let quizSubmissionStarted = false;
let draftKey = `quiz-draft:guest:${subjectId}:${lessonIndex}`;

function saveQuizDraft() {
    if (!quiz.questions.length || quizSubmissionStarted) return;
    try {
        localStorage.setItem(draftKey, JSON.stringify({ questions: quiz.questions, answers, currentQuestion }));
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

async function saveTestResult(score, totalScore = quiz.questions.length) {
    const response = await fetchJsonWithTimeout('test_submit.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            course_name: courseName,
            subject_id: subjectId,
            lesson_index: lessonIndex,
            lesson_no: lessonIndex,
            score,
            total_score: totalScore,
            answers: Object.fromEntries(quiz.questions.map((question, index) => [String(question.question_id), answers[index]]))
        })
    });
    return response.json();
}

function renderAnswerKey() {
    return `
        <div class="answer-key">
            <h3>เฉลยข้อสอบ</h3>
            ${quiz.questions.map((item, index) => {
                const correctIndex = Number(item.answer);
                const selectedIndex = answers[index];
                const correctDisplayIndex = item.optionIndexes?.indexOf(correctIndex) ?? correctIndex;
                const selectedDisplayIndex = item.optionIndexes?.indexOf(Number(selectedIndex)) ?? Number(selectedIndex);
                const isEssay = isEssayQuestion(item);
                const essayAnswerText = String(item.answer_text || '').trim();
                const isCorrect = !isEssay && Number(selectedIndex) === correctIndex;
                return `
                    <div class="answer-item ${isCorrect ? 'is-correct' : 'is-wrong'}">
                        <b>ข้อ ${index + 1}: ${item.question}</b>
                        ${isEssay
                            ? `<p>คำตอบที่ถูก: ${essayAnswerText && essayAnswerText !== '-' ? essayAnswerText : 'รอครูตรวจ'}</p>`
                            : `<p>คำตอบที่ถูก: ${item.options[correctDisplayIndex]}</p>`
                        }
                        <p>คำตอบของคุณ: ${
                            selectedIndex === null || selectedIndex === undefined || selectedIndex === ''
                                ? 'ไม่ได้ตอบ'
                                : isEssay
                                    ? selectedIndex
                                    : item.options[selectedDisplayIndex]
                        }</p>
                    </div>
                `;
            }).join('')}
        </div>
    `;
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

async function showResult() {
    if (quizSubmissionStarted) return;
    quizSubmissionStarted = true;
    saveCurrentAnswer();
    const totalScore = quiz.questions.reduce((total, item) => total + Number(item.max_score || 1), 0);
    const essayCount = quiz.questions.filter((item) => isEssayQuestion(item)).length;
    const hasPendingEssay = quiz.questions.some((item, index) => isEssayQuestion(item) && String(answers[index] || '').trim() !== '');
    const calculatedScore = quiz.questions.reduce((total, item, index) => {
        if (isEssayQuestion(item)) return total;
        return total + (answers[index] === item.answer ? Number(item.max_score || 1) : 0);
    }, 0);
    const score = calculatedScore;
    const requiredScore = totalScore * QUIZ_PASS_RATIO;
    const isPassed = score >= requiredScore;
    const effectivePassed = isPassed;

    resultBox.hidden = false;
    resultBox.innerHTML = `
        <h2>ผลคะแนน</h2>
        <p>คุณได้ <span class="score">${score}/${totalScore}</span> คะแนน</p>
        <p>${isPassed ? 'ผ่านเกณฑ์แล้ว สามารถกลับไปหน้ารายวิชาได้' : hasPendingEssay ? 'ส่งข้อเขียนแล้ว รออาจารย์ตรวจผลก่อน' : 'ยังไม่ผ่านเกณฑ์ กรุณาทำซ้ำบทเดิมอีกครั้ง'}</p>
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
        const result = await saveTestResult(score, totalScore);
        if (result.status === 'unauthorized') {
            window.__lastQuizSaveResult = { ok: false, status: 'unauthorized', error: result.message || 'unauthorized', score, totalScore };
            saveStatus.innerText = '';
            console.warn('[quiz-save] unauthorized', result.message || '');
            return;
        }
        if (result.status === 'success') {
            localStorage.removeItem(draftKey);
            lastResultPassed = result.quiz_status === 'passed';
            window.__lastQuizSaveResult = {
                ok: true,
                status: result.quiz_status || 'success',
                score,
                totalScore,
                requiredScore,
            };
            saveStatus.innerText = 'บันทึกผลแล้ว';
            if (lastResultPassed) {
                const answerKey = document.createElement('div');
                answerKey.innerHTML = renderAnswerKey();
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
            console.info('[quiz-save] success', window.__lastQuizSaveResult);
            const actionWrap = resultBox.querySelector('.result-action-wrap');
            if (actionWrap) {
                actionWrap.outerHTML = renderResultActionButton(lastResultPassed, Boolean(result.pending_review));
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
                score,
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

        draftKey = `quiz-draft:${result.draft_scope || 'guest'}:${subjectId}:${lessonIndex}`;
        let draft = null;
        try { draft = JSON.parse(localStorage.getItem(draftKey) || 'null'); } catch (_) {}
        const serverQuestionIds = result.questions.map((question) => Number(question.question_id)).sort((a, b) => a - b);
        const draftQuestionIds = Array.isArray(draft?.questions) ? draft.questions.map((question) => Number(question.question_id)).sort((a, b) => a - b) : [];
        const validDraft = serverQuestionIds.length === draftQuestionIds.length
            && serverQuestionIds.every((id, index) => id === draftQuestionIds[index])
            && Array.isArray(draft.answers) && draft.answers.length === result.questions.length;
        quiz = {
            subtitle: `ทำแบบทดสอบบทที่ ${lessonIndex}`,
            questions: validDraft ? draft.questions : randomizeQuizQuestions(result.questions)
        };
        answers = validDraft ? draft.answers : new Array(quiz.questions.length).fill(null);
        currentQuestion = validDraft ? Math.max(0, Math.min(quiz.questions.length - 1, Number(draft.currentQuestion) || 0)) : 0;
        quizSubtitle.innerText = quiz.subtitle;
        renderQuestion();
        saveQuizDraft();
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
