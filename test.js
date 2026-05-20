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

function getProgressRow(summary) {
    const rows = Array.isArray(summary?.lessons) ? summary.lessons : [];
    return rows.find((row) => Number(row.lesson_index || 0) === lessonIndex) || {};
}

async function validateQuizAccess() {
    if (!subjectId) return { ok: false, message: 'ไม่พบรหัสรายวิชา' };

    try {
        const [summaryResponse, quizProgressResponse] = await Promise.all([
            fetch(`student_learning_api.php?action=summary&subject_id=${encodeURIComponent(subjectId)}`, { credentials: 'same-origin' }),
            fetch(`api_quiz_progress.php?subject_id=${encodeURIComponent(subjectId)}`, { credentials: 'same-origin' })
        ]);

        const summaryResult = await summaryResponse.json();
        const quizProgressResult = await quizProgressResponse.json();

        if (summaryResult.status !== 'success') {
            return { ok: false, message: 'ไม่สามารถตรวจสอบสิทธิ์การทำข้อสอบได้' };
        }

        const passedLessons = new Set(
            quizProgressResult.status === 'success' && Array.isArray(quizProgressResult.passed_lessons)
                ? quizProgressResult.passed_lessons.map((v) => Number(v))
                : []
        );

        if (lessonIndex > 1 && !passedLessons.has(lessonIndex - 1)) {
            return { ok: false, message: `ต้องสอบผ่านบทที่ ${lessonIndex - 1} ก่อน` };
        }

        const currentRow = getProgressRow(summaryResult.summary || {});
        const openedCount = Number(currentRow.opened_count || 0);
        const videoCompletedCount = Number(currentRow.video_open_count || 0);

        if (openedCount <= 0) {
            return { ok: false, message: 'ต้องอ่านบทเรียนให้จบก่อนทำข้อสอบ' };
        }
        if (videoCompletedCount <= 0) {
            return { ok: false, message: 'ต้องดูวิดีโอให้จบก่อนทำข้อสอบ' };
        }

        return { ok: true, message: '' };
    } catch (error) {
        return { ok: false, message: 'เชื่อมต่อระบบตรวจสอบเงื่อนไขไม่สำเร็จ' };
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

async function showResult() {
    saveCurrentAnswer();
    const score = answers.reduce((total, answer, index) => total + (answer === quiz.questions[index].answer ? 1 : 0), 0);
    const requiredScore = Math.max(1, Math.ceil(quiz.questions.length * 0.6));
    const isPassed = score >= requiredScore;

    resultBox.hidden = false;
    resultBox.innerHTML = `
        <h2>ผลคะแนน</h2>
        <p>คุณได้ <span class="score">${score}/${quiz.questions.length}</span> คะแนน</p>
        <p>${isPassed ? 'ผ่านเกณฑ์แล้ว ไปบทถัดไปได้' : 'ยังไม่ผ่านเกณฑ์ กรุณาทำซ้ำบทเดิมอีกครั้ง'}</p>
        <p>เกณฑ์ผ่าน: ${requiredScore} คะแนน</p>
        ${isPassed ? renderAnswerKey() : ''}
        <p id="saveStatus">กำลังบันทึกผลแบบทดสอบ...</p>
    `;

    quizForm.hidden = true;
    prevBtn.hidden = true;
    nextBtn.hidden = true;
    submitBtn.innerText = 'ทำแบบทดสอบใหม่';
    submitBtn.hidden = false;

    const saveStatus = document.getElementById('saveStatus');
    try {
        const result = await saveTestResult(score);
        if (result.status === 'unauthorized') {
            saveStatus.innerText = result.message || 'กรุณาเข้าสู่ระบบก่อนบันทึกผล';
            return;
        }
        if (result.status === 'success') {
            lastResultPassed = result.quiz_status === 'pass';
            saveStatus.innerText = result.quiz_status === 'pass'
                ? 'บันทึกผลแล้ว: ผ่านบทนี้ สามารถไปบทถัดไปได้'
                : 'บันทึกผลแล้ว: ยังไม่ผ่าน กรุณาทำซ้ำบทนี้';
            const actionHtml = lastResultPassed
                ? `<button id="resultActionBtn" class="btn-primary">ไปบทถัดไป</button>`
                : `<button id="resultActionBtn" class="btn-primary">กลับไปทำใหม่</button>`;
            resultBox.insertAdjacentHTML('beforeend', actionHtml);
            const resultActionBtn = document.getElementById('resultActionBtn');
            if (resultActionBtn) {
                resultActionBtn.addEventListener('click', () => {
                    if (lastResultPassed) {
                        const nextLesson = lessonIndex + 1;
                        if (nextLesson > 5) {
                            window.location.href = backToCourse.href;
                            return;
                        }
                        const nextUrl = new URL('test.html', window.location.href);
                        nextUrl.searchParams.set('course', courseName);
                        nextUrl.searchParams.set('subject_id', subjectId);
                        nextUrl.searchParams.set('lesson', String(nextLesson));
                        window.location.href = nextUrl.pathname.split('/').pop() + nextUrl.search;
                        return;
                    }
                    answers.fill(null);
                    currentQuestion = 0;
                    resultBox.hidden = true;
                    quizForm.hidden = false;
                    prevBtn.hidden = false;
                    submitBtn.innerText = 'ส่งคำตอบ';
                    renderQuestion();
                });
            }
        } else {
            saveStatus.innerText = result.message || 'บันทึกผลไม่สำเร็จ';
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

    const access = await validateQuizAccess();
    if (!access.ok) {
        quizSubtitle.innerText = 'ยังไม่ผ่านเงื่อนไขก่อนทำแบบทดสอบ';
        quizForm.innerHTML = `<p>${access.message}</p>`;
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

    answers.fill(null);
    currentQuestion = 0;
    lastResultPassed = false;
    resultBox.hidden = true;
    quizForm.hidden = false;
    prevBtn.hidden = false;
    submitBtn.innerText = 'ส่งคำตอบ';
    renderQuestion();
});

loadQuizFromDatabase();
