const quizBank = {
    'ภาษาอังกฤษ': {
        subtitle: 'ทบทวนคำศัพท์และการสื่อสารภาษาอังกฤษพื้นฐาน',
        questions: [
            {
                question: 'ข้อใดเป็นคำทักทายที่ใช้ได้สุภาพในการเริ่มบทสนทนา',
                options: ['Good morning', 'Go away', 'Never mind', 'I do not care'],
                answer: 0
            },
            {
                question: 'คำว่า “Thank you” หมายถึงข้อใด',
                options: ['ขอโทษ', 'ขอบคุณ', 'ลาก่อน', 'ไม่เป็นไร'],
                answer: 1
            },
            {
                question: 'ประโยคใดใช้แนะนำตัวได้ถูกต้อง',
                options: ['My name is Anna.', 'I name Anna.', 'Me Anna name.', 'Anna my is name.'],
                answer: 0
            },
            {
                question: 'คำว่า “Please” มักใช้เพื่อแสดงอะไร',
                options: ['ความสุภาพ', 'ความโกรธ', 'การปฏิเสธ', 'การนับจำนวน'],
                answer: 0
            },
            {
                question: 'ประโยค “How are you?” ใช้ถามเกี่ยวกับอะไร',
                options: ['ชื่อ', 'อายุ', 'สบายดีไหม', 'ที่อยู่'],
                answer: 2
            }
        ]
    },
    'ภาษาไทย': {
        subtitle: 'ทบทวนการอ่าน การเขียน และการใช้ภาษาไทย',
        questions: [
            {
                question: 'การอ่านจับใจความสำคัญควรเริ่มจากสิ่งใด',
                options: ['อ่านผ่าน ๆ โดยไม่คิด', 'หาประเด็นหลักของเรื่อง', 'จำทุกคำให้ครบ', 'อ่านเฉพาะประโยคสุดท้าย'],
                answer: 1
            },
            {
                question: 'ข้อใดเป็นประโยคที่ใช้ภาษาเหมาะสมในการทำงาน',
                options: ['ส่งงานให้เดี๋ยวนี้', 'กรุณาส่งงานภายในวันนี้', 'เอางานมา', 'ทำไมยังไม่ส่ง'],
                answer: 1
            },
            {
                question: 'คำใดเป็นคำสุภาพ',
                options: ['กิน', 'รับประทาน', 'แดก', 'เขมือบ'],
                answer: 1
            },
            {
                question: 'การเขียนข้อความที่ดีควรมีลักษณะอย่างไร',
                options: ['วกวน', 'ชัดเจนและตรงประเด็น', 'ยาวที่สุดเท่าที่ทำได้', 'ใช้คำผิดเพื่อดึงดูด'],
                answer: 1
            },
            {
                question: 'เครื่องหมายวรรคตอนช่วยเรื่องใด',
                options: ['ทำให้ข้อความอ่านง่าย', 'ทำให้คำสะกดผิด', 'ทำให้ประโยคไม่มีความหมาย', 'ใช้แทนตัวเลขเท่านั้น'],
                answer: 0
            }
        ]
    },
    'สังคมศึกษา': {
        subtitle: 'ทบทวนหน้าที่พลเมือง วัฒนธรรม และเศรษฐกิจพื้นฐาน',
        questions: [
            {
                question: 'หน้าที่ของพลเมืองที่ดีคือข้อใด',
                options: ['เคารพกฎหมาย', 'หลีกเลี่ยงกฎระเบียบ', 'ทำตามใจตนเองเสมอ', 'ไม่รับฟังผู้อื่น'],
                answer: 0
            },
            {
                question: 'วัฒนธรรมมีความสำคัญอย่างไร',
                options: ['ทำให้สังคมไม่มีระเบียบ', 'สะท้อนวิถีชีวิตและความเชื่อของคนในสังคม', 'ใช้เฉพาะในโรงเรียน', 'ไม่มีผลต่อการอยู่ร่วมกัน'],
                answer: 1
            },
            {
                question: 'ข้อใดเป็นการอยู่ร่วมกันในสังคมอย่างเหมาะสม',
                options: ['ช่วยเหลือและเคารพกัน', 'เอาเปรียบผู้อื่น', 'ไม่สนใจกติกา', 'สร้างความขัดแย้ง'],
                answer: 0
            },
            {
                question: 'เศรษฐกิจพื้นฐานเกี่ยวข้องกับเรื่องใด',
                options: ['การใช้ทรัพยากรและการตัดสินใจใช้จ่าย', 'การวาดภาพเท่านั้น', 'การออกกำลังกายเท่านั้น', 'การแต่งเพลงเท่านั้น'],
                answer: 0
            },
            {
                question: 'การประหยัดเงินช่วยให้เกิดผลดีอย่างไร',
                options: ['มีเงินสำรองในอนาคต', 'ใช้จ่ายเกินตัว', 'มีหนี้มากขึ้น', 'ไม่ต้องวางแผน'],
                answer: 0
            }
        ]
    },
    'คณิตศาสตร์': {
        subtitle: 'ทบทวนการคำนวณและการคิดวิเคราะห์เชิงตัวเลข',
        questions: [
            {
                question: 'ผลลัพธ์ของ 12 + 8 คือข้อใด',
                options: ['18', '19', '20', '21'],
                answer: 2
            },
            {
                question: '50% เท่ากับเศษส่วนใด',
                options: ['1/4', '1/2', '3/4', '2/3'],
                answer: 1
            },
            {
                question: 'ถ้ามีดินสอ 5 แท่ง ซื้อเพิ่ม 3 แท่ง จะมีทั้งหมดกี่แท่ง',
                options: ['7', '8', '9', '10'],
                answer: 1
            },
            {
                question: 'จำนวนใดเป็นเลขคู่',
                options: ['13', '17', '21', '24'],
                answer: 3
            },
            {
                question: '10 x 6 มีค่าเท่าใด',
                options: ['50', '60', '70', '80'],
                answer: 1
            }
        ]
    },
    'วิทยาศาสตร์': {
        subtitle: 'ทบทวนกระบวนการทางวิทยาศาสตร์และความรู้พื้นฐาน',
        questions: [
            {
                question: 'ขั้นตอนแรกของกระบวนการทางวิทยาศาสตร์มักเริ่มจากอะไร',
                options: ['ตั้งคำถามหรือสังเกตปัญหา', 'สรุปผลทันที', 'คัดลอกคำตอบ', 'ละเลยข้อมูล'],
                answer: 0
            },
            {
                question: 'น้ำเปลี่ยนเป็นไอเมื่อได้รับสิ่งใด',
                options: ['ความร้อน', 'ความมืด', 'เสียง', 'แม่เหล็ก'],
                answer: 0
            },
            {
                question: 'พืชใช้สิ่งใดในการสังเคราะห์ด้วยแสง',
                options: ['แสงแดด', 'พลาสติก', 'เหล็ก', 'น้ำมัน'],
                answer: 0
            },
            {
                question: 'ข้อใดเป็นสถานะของสสาร',
                options: ['ของแข็ง', 'ความเร็ว', 'น้ำหนัก', 'ทิศเหนือ'],
                answer: 0
            },
            {
                question: 'เครื่องมือใดใช้วัดอุณหภูมิ',
                options: ['เทอร์มอมิเตอร์', 'ไม้บรรทัด', 'เข็มทิศ', 'นาฬิกา'],
                answer: 0
            }
        ]
    }
};

const params = new URLSearchParams(window.location.search);
const courseName = params.get('course') || 'ภาษาไทย';
const subjectId = params.get('subject_id') || '';
const quiz = quizBank[courseName] || quizBank['ภาษาไทย'];
const answers = new Array(quiz.questions.length).fill(null);

let currentQuestion = 0;

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

quizTitle.innerText = `แบบทดสอบวิชา${courseName}`;
quizSubtitle.innerText = quiz.subtitle;
backToCourse.href = subjectId
    ? `web.html?subject_id=${encodeURIComponent(subjectId)}&course=${encodeURIComponent(courseName)}`
    : `web.html?course=${encodeURIComponent(courseName)}`;

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
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            course_name: courseName,
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
    const score = answers.reduce((total, answer, index) => {
        return total + (answer === quiz.questions[index].answer ? 1 : 0);
    }, 0);
    const isPassed = score >= 3;

    resultBox.hidden = false;
    resultBox.innerHTML = `
        <h2>ผลคะแนน</h2>
        <p>คุณได้ <span class="score">${score}/${quiz.questions.length}</span> คะแนน</p>
        <p>${isPassed ? 'ผ่านเกณฑ์แบบทดสอบแล้ว' : 'ยังไม่ผ่านเกณฑ์ ลองทบทวนบทเรียนแล้วทำใหม่อีกครั้ง'}</p>
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
        saveStatus.innerText = result.status === 'success'
            ? 'บันทึกผลลงตาราง test เรียบร้อย'
            : 'บันทึกผลไม่สำเร็จ';
    } catch (error) {
        saveStatus.innerText = 'เชื่อมต่อระบบบันทึกผลไม่ได้';
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
    resultBox.hidden = true;
    quizForm.hidden = false;
    prevBtn.hidden = false;
    submitBtn.innerText = 'ส่งคำตอบ';
    renderQuestion();
});

renderQuestion();
