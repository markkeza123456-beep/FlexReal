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

let quiz = { subtitle: '', questions: [] };
let answers = [];
let currentQuestion = 0;
let lastResultPassed = false;

quizTitle.innerText = `แบบทดสอบวิชา ${courseName} (บทที่ ${lessonIndex})`;
quizSubtitle.innerText = 'กำลังโหลดข้อสอบจากฐานข้อมูล...';
backToCourse.href = subjectId
    ? `web.html?subject_id=${encodeURIComponent(subjectId)}&course=${encodeURIComponent(courseName)}`
    : `web.html?course=${encodeURIComponent(courseName)}`;

function renderLessonResults(lessonResults) {
    if (!Array.isArray(lessonResults) || lessonResults.length === 0) {
        return '<p class="lesson-status-empty">ยังไม่มีข้อมูลคะแนนของแต่ละบท</p>';
    }

    return `
        <div class="lesson-status-list">
            ${lessonResults.map((row) => {
                const statusRaw = (row.status || '').toLowerCase();
                const isPass = statusRaw === 'pass';
                const score = Number(row.score || 0);
                const total = Math.max(0, Number(row.total_score || 0));
                return `
                    <div class="lesson-status-item ${isPass ? 'is-pass' : 'is-fail'}">
                        <span>บทที่ ${Number(row.lesson_no || 0)}</span>
                        <span>${isPass ? 'ผ่าน' : 'ไม่ผ่าน'} (${score}/${total})</span>
                    </div>
                `;
            }).join('')}
        </div>
    `;
}

async function fetchLessonResultsHtml() {
    if (!subjectId) {
        return '<p class="lesson-status-empty">ไม่พบรหัสรายวิชา</p>';
    }
    try {
        const response = await fetch(`api_quiz_progress.php?subject_id=${encodeURIComponent(subjectId)}`, {
            credentials: 'same-origin'
        });
        const result = await response.json();
        if (result.status !== 'success') {
            return '<p class="lesson-status-empty">โหลดสถานะแต่ละบทไม่สำเร็จ</p>';
        }
        return renderLessonResults(result.lesson_results);
    } catch (error) {
        return '<p class="lesson-status-empty">เชื่อมต่อข้อมูลสถานะแต่ละบทไม่สำเร็จ</p>';
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
    const response = await fetch('test_submit.php', {
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
    const requiredScore = Math.max(1, Math.ceil(quiz.questions.length * 0.6));
    const isPassed = score >= requiredScore;

    resultBox.hidden = false;
    resultBox.innerHTML = `
        <h2>ผลคะแนน</h2>
        <p>คุณได้ <span class="score">${score}/${quiz.questions.length}</span> คะแนน</p>
        <p>${isPassed ? 'ผ่านเกณฑ์แล้ว สามารถกลับไปหน้ารายวิชาได้' : 'ยังไม่ผ่านเกณฑ์ กรุณาทำซ้ำบทเดิมอีกครั้ง'}</p>
        <p>เกณฑ์ผ่าน: ${requiredScore} คะแนน</p>
        <h3>สถานะแต่ละบท</h3>
        <div id="lessonResultsBox"><p class="lesson-status-empty">กำลังโหลดสถานะแต่ละบท...</p></div>
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
    const lessonResultsBox = document.getElementById('lessonResultsBox');
    try {
        const result = await saveTestResult(score);
        if (result.status === 'unauthorized') {
            saveStatus.innerText = result.message || 'กรุณาเข้าสู่ระบบก่อนบันทึกผล';
            return;
        }
        if (result.status === 'success') {
            lastResultPassed = result.quiz_status === 'pass';
            saveStatus.innerText = result.quiz_status === 'pass'
                ? 'บันทึกผลแล้ว: ผ่านบทนี้'
                : 'บันทึกผลแล้ว: ยังไม่ผ่าน กรุณาทำซ้ำบทนี้';
            const actionWrap = resultBox.querySelector('.result-action-wrap');
            if (actionWrap) {
                actionWrap.outerHTML = renderResultActionButton(lastResultPassed);
                bindResultAction(lastResultPassed);
            }
            if (lessonResultsBox) {
                lessonResultsBox.innerHTML = await fetchLessonResultsHtml();
            }
        } else {
            saveStatus.innerText = result.message || 'บันทึกผลไม่สำเร็จ กรุณาลองใหม่อีกครั้ง';
            if (lessonResultsBox) {
                lessonResultsBox.innerHTML = await fetchLessonResultsHtml();
            }
        }
    } catch (error) {
        saveStatus.innerText = 'เชื่อมต่อระบบบันทึกผลไม่ได้';
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
        const response = await fetch(`api_quiz.php?action=get_questions&subject_id=${encodeURIComponent(subjectId)}&lesson=${lessonIndex}`, {
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
        quizForm.innerHTML = '<p>ไม่สามารถเชื่อมต่อฐานข้อมูลข้อสอบได้</p>';
        prevBtn.hidden = true;
        nextBtn.hidden = true;
        submitBtn.hidden = true;
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
