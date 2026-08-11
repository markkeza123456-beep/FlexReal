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
const QUIZ_PASS_RATIO = 0.8;
const REQUEST_TIMEOUT_MS = 8000;

let quiz = { subtitle: '', questions: [] };
let answers = [];
let currentQuestion = 0;
let lastResultPassed = false;

quizTitle.innerText = `แบบทดสอบวิชา ${courseName} (บทที่ ${lessonIndex})`;
quizSubtitle.innerText = 'กำลังโหลดข้อสอบจากฐานข้อมูล...';
backToCourse.href = subjectId
    ? `web.html?subject_id=${encodeURIComponent(subjectId)}&course=${encodeURIComponent(courseName)}`
    : `web.html?course=${encodeURIComponent(courseName)}`;

async function fetchJsonWithTimeout(url, options = {}, timeoutMs = REQUEST_TIMEOUT_MS) {
    const controller = new AbortController();
    const timer = window.setTimeout(() => controller.abort(), timeoutMs);
    try {
        return await fetch(url, { ...options, signal: controller.signal });
    } finally {
        window.clearTimeout(timer);
    }
}

function renderQuestion() {
    const item = quiz.questions[currentQuestion];
    progressText.innerText = `ข้อ ${currentQuestion + 1} จาก ${quiz.questions.length}`;
    progressFill.style.width = `${((currentQuestion + 1) / quiz.questions.length) * 100}%`;

    quizForm.innerHTML = `
        <h2 class="question-title">${currentQuestion + 1}. ${item.question}</h2>
        <div class="option-list">
            ${item.options.map((option, index) => `
                <label class="option-card">
                    <input type="radio" name="answer" value="${index}" ${answers[currentQuestion] === index ? 'checked' : ''}>
                    <span>${option}</span>
                </label>
            `).join('')}
        </div>
    `;

    prevBtn.disabled = currentQuestion === 0;
    nextBtn.hidden = currentQuestion === quiz.questions.length - 1;
    submitBtn.hidden = currentQuestion !== quiz.questions.length - 1;
}

function saveCurrentAnswer() {
    const selected = quizForm.querySelector('input[name="answer"]:checked');
    answers[currentQuestion] = selected ? Number(selected.value) : null;
}

async function saveTestResult(score) {
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
            total_score: quiz.questions.length,
            answers
        })
    });
    return response.json();
}

function renderAnswerKey() {
    return `
        <div class="answer-key">
            <h3>เฉลยข้อสอบ</h3>
            ${quiz.questions.map((item, index) => {
                const correctIndex = item.answer;
                const selectedIndex = answers[index];
                const isCorrect = selectedIndex === correctIndex;
                return `
                    <div class="answer-item ${isCorrect ? 'is-correct' : 'is-wrong'}">
                        <b>ข้อ ${index + 1}: ${item.question}</b>
                        <p>คำตอบที่ถูก: ${item.options[correctIndex]}</p>
                        <p>คำตอบของคุณ: ${selectedIndex === null ? 'ไม่ได้ตอบ' : item.options[selectedIndex]}</p>
                    </div>
                `;
            }).join('')}
        </div>
    `;
}

function renderResultActionButton(isPassed) {
    if (isPassed) return '';
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
    saveCurrentAnswer();
    const score = answers.reduce((total, answer, index) => total + (answer === quiz.questions[index].answer ? 1 : 0), 0);
    const requiredScore = Math.max(1, Math.ceil(quiz.questions.length * QUIZ_PASS_RATIO));
    const isPassed = score >= requiredScore;

    resultBox.hidden = false;
    resultBox.innerHTML = `
        <h2>ผลคะแนน</h2>
        <p>คุณได้ <span class="score">${score}/${quiz.questions.length}</span> คะแนน</p>
        <p>${isPassed ? 'ผ่านเกณฑ์แล้ว สามารถกลับไปหน้ารายวิชาได้' : 'ยังไม่ผ่านเกณฑ์ กรุณาทำซ้ำบทเดิมอีกครั้ง'}</p>
        <p>เกณฑ์ผ่าน: ${requiredScore} คะแนน</p>
        ${isPassed ? renderAnswerKey() : ''}
        <p id="saveStatus">กำลังบันทึกผลแบบทดสอบ...</p>
        ${renderResultActionButton(isPassed)}
    `;
    bindResultAction(isPassed);

    quizForm.hidden = true;
    prevBtn.hidden = true;
    nextBtn.hidden = true;
    submitBtn.hidden = true;
    if (quizActions) quizActions.hidden = true;

    const saveStatus = document.getElementById('saveStatus');
    window.__lastQuizSaveResult = { ok: false, status: 'pending', score, totalScore: quiz.questions.length };
    try {
        const result = await saveTestResult(score);
        if (result.status === 'unauthorized') {
            window.__lastQuizSaveResult = { ok: false, status: 'unauthorized', error: result.message || 'unauthorized', score, totalScore: quiz.questions.length };
            saveStatus.innerText = '';
            console.warn('[quiz-save] unauthorized', result.message || '');
            return;
        }
        if (result.status === 'success') {
            lastResultPassed = result.quiz_status === 'pass';
            window.__lastQuizSaveResult = {
                ok: true,
                status: result.quiz_status || 'success',
                score,
                totalScore: quiz.questions.length,
                requiredScore,
            };
            saveStatus.innerText = 'บันทึกผลแล้ว';
            console.info('[quiz-save] success', window.__lastQuizSaveResult);
            const actionWrap = resultBox.querySelector('.result-action-wrap');
            if (actionWrap) {
                actionWrap.outerHTML = renderResultActionButton(lastResultPassed);
                bindResultAction(lastResultPassed);
            }
        } else {
            window.__lastQuizSaveResult = {
                ok: false,
                status: result.status || 'error',
                error: result.message || 'save_failed',
                score,
                totalScore: quiz.questions.length,
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
            totalScore: quiz.questions.length,
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

        if (result.status !== 'success' || !Array.isArray(result.questions) || result.questions.length === 0) {
            quizSubtitle.innerText = 'ยังไม่มีข้อสอบของบทนี้ในฐานข้อมูล';
            quizForm.innerHTML = '<p>ยังไม่มีข้อสอบ กรุณาติดต่อผู้สอนเพื่อเพิ่มคำถาม</p>';
            prevBtn.hidden = true;
            nextBtn.hidden = true;
            submitBtn.hidden = true;
            return;
        }

        quiz = {
            subtitle: `ทำแบบทดสอบบทที่ ${lessonIndex}`,
            questions: result.questions
        };
        answers = new Array(quiz.questions.length).fill(null);
        currentQuestion = 0;
        quizSubtitle.innerText = quiz.subtitle;
        renderQuestion();
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
    if (answers[currentQuestion] === null) {
        alert('กรุณาเลือกคำตอบก่อน');
        return;
    }
    currentQuestion += 1;
    renderQuestion();
});

prevBtn.addEventListener('click', () => {
    saveCurrentAnswer();
    currentQuestion -= 1;
    renderQuestion();
});

submitBtn.addEventListener('click', () => {
    if (!quizForm.hidden) {
        if (quizForm.querySelector('input[name="answer"]:checked') === null) {
            alert('กรุณาเลือกคำตอบก่อน');
            return;
        }
        showResult();
        return;
    }

    window.location.href = backToCourse.href;
});

loadQuizFromDatabase();
