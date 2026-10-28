# คู่มือพรีเซนต์ Flexible Learning Hub (60 นาที)

> คู่มือนี้อธิบายด้วยภาษาง่าย และแยก “ไฟล์ที่คนใช้เห็น” ออกจาก “ไฟล์เบื้องหลัง” เพื่อให้เล่าได้โดยไม่ต้องจำโค้ดทุกบรรทัด

## คำอธิบายระบบแบบสั้น

Flexible Learning Hub เป็นเว็บเรียนออนไลน์ที่รวมการจัดหลักสูตรและวิชา การเรียนและทำข้อสอบ การติดตามผล และงานดูแลผู้เรียนไว้ในระบบเดียว ผู้ใช้มี 5 บทบาทหลัก ได้แก่ นักเรียน ผู้ปกครอง อาจารย์ เจ้าหน้าที่ และผู้อำนวยการ โดยแต่ละบทบาทเห็นข้อมูลและทำงานตามสิทธิ์ของตน

## สิ่งที่ยืนยันจากการตรวจรอบนี้ (28 ต.ค. 2026)

- หน้าแรกบน [เว็บไซต์สาธารณะ](https://flexible-lerning-hub.onrender.com/) ตอบ HTTP 200 และแสดงรายวิชา 5 วิชา รวมถึงส่วนคู่มือการเรียนและติดต่อเรา
- ลิงก์เข้าสู่ระบบและสมัครสมาชิกเปิดได้ ส่วนหน้า dashboard ต้องเข้าสู่ระบบก่อน จึงไม่ควรพรีหน้าภายในด้วยการเปิด URL ตรงโดยไม่มี session
- ฐาน PostgreSQL schema `public` เชื่อมต่อได้จากเครื่องพัฒนา และมี **25 ตาราง** ตรวจชื่อและจำนวนแถวรวมแบบ read-only; ตัวเลขด้านล่างเป็น snapshot วันนี้ ไม่ใช่ข้อมูลรับประกันว่าจะคงเดิม
- หน้าแรกมี HTML ขนาดประมาณ 2.29 MB ในการตรวจครั้งนี้ ควรเปิดหน้าไว้ล่วงหน้าและทดสอบเครือข่ายก่อนเริ่มนำเสนอ

### จำนวนแถวในฐานข้อมูล ณ วันที่ตรวจ

| กลุ่ม | ตาราง | จำนวนแถว |
|---|---|---:|
| บัญชี/โปรไฟล์ | `users`, `students`, `parents`, `teachers`, `staff` | 14, 8, 1, 3, 2 |
| รายวิชา/หลักสูตร | `courses`, `lessons`, `lesson_resources`, `course_teachers`, `curricula`, `curriculum_courses`, `student_curricula`, `student_courses` | 5, 10, 26, 8, 1, 5, 8, 33 |
| เรียน/สอบ | `lesson_progress`, `questions`, `quiz_attempt_sessions`, `quiz_attempts`, `quiz_attempt_answers` | 70, 20, 24, 27, 53 |
| คำขอ/บันทึกระบบ | `graduation_requests`, `transcript_requests`, `transcript_request_lessons`, `transfer_permissions`, `user_addresses`, `password_reset_tokens`, `site_visits` | 3, 9, 66, 3, 12, 8, 293 |

ฐานนี้มีข้อมูลจริง ใช้เฉพาะภาพรวมเชิงจำนวนในการพรีเซนต์ หลีกเลี่ยงการเปิดชื่อ เลขประจำตัว เบอร์โทร ที่อยู่ รหัสผ่าน/แฮช หรือ token ให้ผู้ชมเห็น

## แผนพรีเซนต์ 1 ชั่วโมง

| เวลา | หัวข้อ | สิ่งที่ควรพูด/สาธิต |
|---|---|---|
| 0–5 นาที | ปัญหาและเป้าหมาย | อธิบายว่าระบบรวมการเรียน การสอบ และการติดตามผู้เรียนไว้ที่เดียว |
| 5–12 นาที | ภาพรวมระบบ | แนะนำบทบาททั้ง 5 และเส้นทางข้อมูลจากหน้าเว็บ → PHP → PostgreSQL → JSON กลับหน้าเว็บ; ใช้หน้าแรกจากลิงก์สาธารณะเป็นจุดเริ่ม |
| 12–25 นาที | มุมมองนักเรียน | เข้าหน้านักเรียน เปิดวิชา/บทเรียน ทำข้อสอบ ดูความคืบหน้าและ timeline กิจกรรม |
| 25–35 นาที | มุมมองอาจารย์ | เปิดรายวิชาที่ดูแล ดูผู้เรียน ตรวจงาน/ข้อสอบ และกรองกิจกรรมย้อนหลัง |
| 35–45 นาที | มุมมองเจ้าหน้าที่ | แสดงการจัดหลักสูตร วิชา บทเรียน และสมาชิก |
| 45–52 นาที | มุมมองผู้อำนวยการ/ผู้ปกครอง | แสดงรายงานภาพรวม หรือความคืบหน้าของนักเรียนที่เชื่อมบัญชี |
| 52–57 นาที | ความปลอดภัยและข้อมูล | อธิบาย session, role authorization, password hashing, prepared statements และการเก็บ secrets ใน environment |
| 57–60 นาที | สรุป/ถามตอบ | ทวนผลลัพธ์หลักและเปิดประเด็นถามตอบ |

## เส้นทางการทำงานที่ควรอธิบาย

1. ผู้ใช้ส่งแบบฟอร์มเข้าสู่ระบบที่ `login.php`
2. `login_action.php` ตรวจรูปแบบข้อมูล ค้นบัญชีและ role ใน PostgreSQL ตรวจรหัสผ่านด้วย `password_verify()` แล้วสร้าง session
3. หน้า dashboard ของ role โหลด HTML/CSS/JavaScript และเรียก API ที่เหมาะกับสิทธิ์
4. API ตรวจ session/role อีกครั้ง ใช้ PDO prepared statements อ่านหรือบันทึกข้อมูล
5. JavaScript แปลง JSON เป็นตาราง การ์ด หรือ timeline โดยไม่ฝัง query ฐานข้อมูลไว้ในหน้าเว็บ

## มองระบบเป็นภาพเดียว

นึกภาพว่าเว็บเป็นโรงเรียน:

| ในระบบ | เปรียบเทียบง่ายๆ | ทำหน้าที่ |
|---|---|---|
| หน้าเว็บ PHP | เคาน์เตอร์/แบบฟอร์ม | แสดงหน้า รับข้อมูล และวางโครงหน้า |
| JavaScript | พนักงานรับส่งเอกสาร | ขอข้อมูลจาก API แล้วเติมผลลงในหน้าปัจจุบัน |
| API / action PHP | เจ้าหน้าที่หลังเคาน์เตอร์ | ตรวจคำขอ ตรวจสิทธิ์ คุยกับฐาน แล้วตอบผล |
| `config/db.php` | สายเชื่อมต่อสำนักงาน | เปิดทางให้ PHP ติดต่อ PostgreSQL โดยใช้ค่าลับจาก environment |
| PostgreSQL | ตู้แฟ้มกลาง | เก็บบัญชี วิชา บทเรียน คะแนน และคำขอต่างๆ |
| `migrations/*.sql` | ใบงานปรับตู้แฟ้ม | เพิ่ม/แก้โครงสร้างฐานข้อมูลเป็นขั้นตอน |

ตัวอย่างการดูหน้า Dashboard: browser เปิด `student_dashboard.php` → หน้านั้นโหลด `assets/js/student_dashboard.js` → JavaScript ขอข้อมูลจาก `student_dashboard_api.php` → API ตรวจ session และ query ผ่าน PDO → PostgreSQL ส่งแถวข้อมูลกลับ → API ตอบ JSON → JavaScript แสดงการ์ด/ตาราง

> หลักจำ: **หน้าแสดงผล → JavaScript ขอข้อมูล → API ตรวจสิทธิ์และคุยฐาน → ฐานส่งข้อมูลกลับ → หน้าแสดงผล**

## อธิบายไฟล์หลักแบบไม่ต้องเขียนโปรแกรมเป็น

### หน้าเว็บที่เปิดในเบราว์เซอร์

| ไฟล์ | เก็บ/ทำอะไร | พูดแบบง่าย |
|---|---|---|
| `index.php` | หน้าแรก แสดงภาพรวมและรายการวิชา | “หน้าต้อนรับและแคตตาล็อกวิชา” |
| `login.php` | แบบฟอร์มเข้าสู่ระบบ | “รับเลขบัญชีและรหัสผ่าน ยังไม่ได้ตรวจฐานเอง” |
| `regisstu.php`, `regisss.php` | แบบฟอร์มสมัครของผู้เรียน/ผู้ใช้กลุ่มที่รองรับ | “หน้ากรอกข้อมูลสมัคร” |
| `student_dashboard.php` | โครงหน้า Dashboard นักเรียน | “จัดวางตำแหน่งการ์ดและตาราง ไม่ใช่ที่เก็บคะแนน” |
| `teacherdash.php` | โครงหน้า Dashboard อาจารย์ | “หน้าอาจารย์เรียกดูและจัดการเฉพาะส่วนที่มีสิทธิ์” |
| `staffdash.php` | โครงหน้าเจ้าหน้าที่ | “ศูนย์จัดการข้อมูลและงานปฏิบัติการ” |
| `director_dash.php` | โครงหน้าผู้อำนวยการ | “หน้าภาพรวมและติดตามคำขอ/รายงาน” |
| `parent_dashboard.php` | โครงหน้าผู้ปกครอง | “หน้าดูข้อมูลผู้เรียนที่เชื่อมกับผู้ปกครอง” |
| `officer-dashboard.php` | หน้าสำหรับงานเจ้าหน้าที่/ผู้ดูแลบางส่วน | “อีกหน้าหนึ่งของงานปฏิบัติการ ตรวจ role จริงก่อนพรี” |

### เข้าสู่ระบบและสมัครบัญชี

| ไฟล์ | เก็บ/ทำอะไร |
|---|---|
| `login_action.php` | รับข้อมูลจากฟอร์ม ตรวจบัญชีและ `password_hash` ด้วย `password_verify`, ตรวจสถานะ/บทบาท แล้วตั้ง session และส่งไปหน้า role |
| `logout.php` | ล้าง session เพื่อออกจากระบบ |
| `regisstu_action.php`, `regisss_action.php` | รับข้อมูลสมัคร ตรวจและบันทึกบัญชี/โปรไฟล์ |
| `registration_lib.php` | รวมกติกาหรือฟังก์ชันสมัครที่หลายหน้าใช้ร่วมกัน |
| `registration_otp.php`, `registration_otp_action.php`, `registration_mailer.php` | ขั้นตอนรหัส OTP และการส่งอีเมล (ขึ้นกับการตั้งค่าบริการภายนอก) |
| `forgot_password.php`, `forgot_password_action.php`, `reset_password_action.php` | ขอรีเซ็ตและบันทึกรหัสผ่านใหม่ |
| `student_forgot_pin.php`, `verify_pin_action.php`, `student_pin_reset_action.php` | flow PIN/ยืนยันตัวตนสำหรับผู้เรียน |
| `account_status_lib.php` | ฟังก์ชันเกี่ยวกับสถานะบัญชี |

คำพูดง่ายๆ: รหัสผ่านไม่ควรถูกบันทึกเป็นข้อความอ่านได้ ฐานเก็บค่า hash; ตอนล็อกอินระบบนำรหัสที่กรอกมาตรวจด้วย `password_verify()` แล้วจึงสร้าง session เพื่อจำว่าผู้ใช้คนนี้ล็อกอินและมี role อะไร

### ไฟล์ข้อมูล/API ที่สำคัญ

| ไฟล์ | ข้อมูลที่อ่าน/เขียน | คำอธิบายง่าย |
|---|---|---|
| `config/db.php` | ค่าต่อฐานจาก environment และสร้าง PDO connection | “ตัวเปิดสายไปฐานข้อมูล; ไม่ควรเปิดเผยไฟล์ env หรือรหัสผ่าน” |
| `check_auth.php`, `student_session.php` | session/role | “ยามตรวจบัตรก่อนให้เข้าพื้นที่” |
| `student_dashboard_api.php` | วิชาที่เรียน ความคืบหน้า คะแนน/ข้อมูลการ์ด | “ส่งข้อมูลหลังบ้านของหน้า Dashboard นักเรียนเป็น JSON” |
| `teacher_api.php`, `teacher_report.php` | ข้อมูลผู้เรียน/รายงานที่อาจารย์ได้รับอนุญาต | “ข้อมูลสำหรับงานอาจารย์ โดยจำกัดตามวิชาหรือสิทธิ์” |
| `api_staff.php` | งานจัดการของเจ้าหน้าที่ | “รับคำสั่งจากหน้าเจ้าหน้าที่แล้วอ่าน/แก้ข้อมูลตามสิทธิ์” |
| `parent_dashboard_api.php`, `parent_login_api.php`, `parent_quiz_details_api.php` | ข้อมูลลูกที่เชื่อมบัญชีและรายละเอียดผลสอบ | “ให้ผู้ปกครองดูเฉพาะผู้เรียนที่ผูกความสัมพันธ์ไว้” |
| `learning_progress_report_api.php` | เหตุการณ์เรียน/เริ่มสอบ/ส่งสอบตามช่วงวัน และแบ่งหน้า | “จัด timeline จากหลายแหล่งให้เป็นรายการตามเวลา” |
| `api_courses.php`, `course_enrollment_api.php`, `course_progress.php` | รายวิชา การลงทะเบียน และความคืบหน้ารายวิชา | “จัดการข้อมูลวิชาและสถานะการเรียน” |
| `api_quiz.php`, `quiz_session.php`, `test_submit.php` | โหลดข้อสอบ เริ่ม session ส่งคำตอบและบันทึก attempt | “ข้อสอบมีทั้งรอบที่เริ่มและผลเมื่อส่ง” |
| `api_quiz_progress.php`, `api_save_progress.php`, `save_progress.php` | บันทึกตำแหน่งอ่าน/ดูสื่อและความคืบหน้าบทเรียน | “จำว่าผู้เรียนอ่าน/ดูถึงไหนเพื่อกลับมาเรียนต่อ” |
| `learning_progress_lib.php`, `curriculum_subjects_lib.php` | กติกาความคืบหน้าและวิชาในหลักสูตรที่ใช้ซ้ำ | “กล่องเครื่องมือกลาง ลดการเขียนกติกาซ้ำ” |
| `course_certificate_api.php`, `course_certificate_lib.php`, `download_course_certificate.php` | สร้าง/ตรวจ/ดาวน์โหลดใบรับรองรายวิชา | “เอกสารรับรองหลังเรียนครบตามเงื่อนไข” |

### งานเอกสารและคำขอ

| กลุ่มไฟล์ | หน้าที่ |
|---|---|
| `upload_transcript.php`, `api/upload_transcript.php`, `transcript_storage.php`, `includes/transcript_upload_data.php` | รับและจัดเก็บเอกสารผลการเรียน |
| `review_transcript.php`, `process_transcript.php`, `reject_transcript.php`, `includes/transcript_review_data.php` | ตรวจ อนุมัติ หรือปฏิเสธคำขอ/เอกสาร |
| `submit_transfer.php`, `credit_transfer.php`, `teacher_credit_transfers.php`, `staff_transfer_permissions.php`, `assign_permission.php` | ยื่นเทียบโอน ตรวจและกำหนดสิทธิ์ผู้ตรวจ |
| `graduation_tracker_api.php`, `staff_graduation_api.php`, `director_graduation_status.php`, `export_director_report.php`, `reports.php` | ติดตามการจบการศึกษาและสร้างรายงาน |
| `graduation_document_lib.php`, `download_graduation_document.php` | สร้าง/ดาวน์โหลดเอกสารเกี่ยวกับการจบ |
| `get_student_detail.php`, `get_student_quiz_answers.php` | ดึงรายละเอียดผู้เรียนหรือคำตอบเพื่อผู้มีสิทธิ์ตรวจ |
| `staff_subject_editor.php`, `staff_video_editor.php`, `teacher_video_editor.php` | หน้าจัดการรายวิชา/วิดีโอของเจ้าหน้าที่หรือผู้สอน |
| `staff_transfer_permissions.php`, `staff_graduation_api.php`, `teacher_credit_transfers.php`, `teacher_graduation.php` | กำหนดผู้ตรวจและจัดการคำขอเทียบโอน/จบการศึกษา |
| `director_profile.php`, `director_student_detail.php`, `director_graduation_status.php` | โปรไฟล์ผู้อำนวยการ รายละเอียดผู้เรียน และสถานะการจบ |
| `parent_add_child.php` | เชื่อมบัญชีผู้เรียนกับบัญชีผู้ปกครอง |
| `ping.php` | ตรวจว่าเว็บตอบสนองหรือไม่ ไม่ใช่ API ข้อมูลผู้เรียน |
| `update_profile.php`, `update_student_profile.php`, `update_parent_profile.php`, `update_staff_profile.php` | แก้ข้อมูลโปรไฟล์แต่ละกลุ่ม |
| `uploadavatar.php`, `uploadavatar_parent.php`, `uploadavatar_staff.php`, `uploadavatar_student.php`, `avatar_storage.php` | รับและจัดการรูปโปรไฟล์ |
| `view_course_lesson.php`, `download_course_lesson.php`, `subject_image.php` | แสดง/ดาวน์โหลดบทเรียนและรูปวิชา |

### ไฟล์หน้าตาเว็บ

- `assets/css/*.css` กำหนดสี ระยะห่าง ขนาด ปุ่ม ตาราง และการจัดวางของหน้าต่างๆ เช่น `student_dashboard.css`, `teacherdash.css`, `parent_dashboard.css`
- `assets/js/*.js` ทำให้หน้าโต้ตอบได้ เช่น โหลดข้อมูล กดปุ่ม ค้นหา/แบ่งหน้า และส่งแบบฟอร์ม
- `assets/js/learning_progress_report.js` วาด timeline และควบคุมปุ่มวันนี้/3/7 วันกับแบ่งหน้า
- `assets/css/learning-progress-report.css` สไตล์ timeline ที่ใช้ร่วมกันหลายบทบาท
- `includes/header.php`, `includes/footer.php`, `components/staff_sidebar.php` คือชิ้นส่วนหน้าเว็บที่นำกลับมาใช้ซ้ำ
- `assets/vendor/tabler-icons/` เป็นชุดไอคอนที่โปรเจกต์แนบไว้ ไม่ใช่ตรรกะธุรกิจ

### ตัวช่วยด้านไฟล์/ภาพ/สื่อ

| ไฟล์ | หน้าที่ |
|---|---|
| `upload.php`, `upload_transcript.php`, `api/upload_transcript.php` | รับไฟล์จากฟอร์ม (ต้องตรวจชนิดไฟล์และสิทธิ์) |
| `transcript_storage.php`, `avatar_storage.php` | ฟังก์ชันจัดเก็บ/เรียกไฟล์เอกสารและรูป |
| `download_course_lesson.php`, `download_course_certificate.php`, `download_graduation_document.php` | ส่งไฟล์ให้ผู้ใช้ที่ผ่านการตรวจสิทธิ์ |
| `subject_image.php`, `view_course_lesson.php` | แสดงรูปวิชาและเนื้อหาบทเรียน |
| `course_enrollment_api.php`, `course_progress.php` | การลงทะเบียนและอ่านความคืบหน้ารายวิชา |
| `api_save_progress.php`, `save_progress.php` | บันทึกความคืบหน้า; ชื่อคล้ายกัน จึงควรตรวจจุดเรียกใช้งานก่อนบอกว่าแทนกันได้ |

ไม่จำเป็นต้องท่อง CSS ทุกไฟล์: ถ้าถูกถามเรื่อง “ข้อมูลมาจากไหน” ให้ชี้ API; ถ้าถูกถาม “หน้าตาอยู่ไหน” ให้ชี้ CSS/JavaScript ของหน้านั้น

## ฐานข้อมูลแบบเข้าใจง่าย

ตารางคือแผ่นงานหลายแผ่นที่เชื่อมกันด้วยรหัส เช่น `user_id`, `course_id`, `lesson_id` แทนการเขียนทุกอย่างกองในแผ่นเดียว ฐานปัจจุบันเป็น PostgreSQL schema `public` และมี 25 ตาราง (ตรวจ 28 ต.ค. 2026) ตารางจริง ณ วันตรวจแบ่งได้ดังนี้:

| ตาราง | เก็บอะไร | ความสัมพันธ์แบบง่าย |
|---|---|---|
| `users` | บัญชีเข้าสู่ระบบ, role, สถานะ และ hash รหัสผ่าน | จุดกลางของบัญชีทั้งหมด |
| `students`, `parents`, `teachers`, `staff` | ข้อมูลโปรไฟล์แยกตามบทบาท | `user_id` เชื่อมกลับ `users` |
| `user_addresses` | ที่อยู่ผู้ใช้ | เป็นข้อมูลเสริมของบัญชี |
| `password_reset_tokens` | hash token และเวลาหมดอายุ/ใช้แล้ว | ใช้ flow รีเซ็ตรหัสผ่าน ไม่เก็บ token ใช้งานแบบเปิดเผย |
| `site_visits` | เวลาและ role ของการเข้าใช้ | ใช้ข้อมูลภาพรวมการเข้าใช้งาน |
| `courses` | ชื่อวิชา รหัส หน่วยกิต สถานะ/ประเภท | วิชาหนึ่งมีหลายบท |
| `lessons` | ชื่อ/ลำดับ/เกณฑ์ผ่านของบท | แต่ละแถวสังกัด `courses` |
| `lesson_resources` | สื่อหรือไฟล์ประกอบบทเรียน | แต่ละแถวผูกกับ `lessons` |
| `course_teachers` | ผู้สอนที่รับผิดชอบวิชา | ตารางเชื่อมครูกับวิชา |
| `curricula` | หลักสูตร | หลักสูตรหนึ่งประกอบด้วยหลายวิชา |
| `curriculum_courses` | วิชาในหลักสูตรและชนิดวิชาบังคับ/เลือก | ตารางเชื่อมหลักสูตรกับวิชา |
| `student_curricula`, `student_courses` | การลงทะเบียนหลักสูตร/รายวิชาของนักเรียน | ตารางเชื่อมผู้เรียนกับสิ่งที่เรียน |
| `lesson_progress` | จุดที่อ่าน/ดูสื่อ จำนวนเปิด และความคืบหน้ารายบท | เชื่อมผู้เรียนกับบทเรียน |
| `questions` | คำถาม ตัวเลือก คะแนนเต็ม และเฉลยสำหรับระบบ | แต่ละข้อผูกกับบทเรียน; จำกัดการเปิดเผยเฉลย |
| `quiz_attempt_sessions` | การเริ่มสอบ เวลา/สถานะ/เลขรอบ | ใช้รู้ว่าเริ่มสอบเมื่อใด |
| `quiz_attempts` | คะแนน สถานะ และเวลาส่งของแต่ละรอบ | ผู้เรียนทำบทเดียวกันได้หลาย attempt ตามกติกา |
| `quiz_attempt_answers` | คำตอบและผลตรวจรายข้อ | แต่ละคำตอบผูกกับ attempt และ question |
| `transcript_requests`, `transcript_request_lessons` | คำขอเอกสารและรายการบทเรียนที่เกี่ยวข้อง | ตารางรายการแยกจากหัวคำขอ |
| `graduation_requests` | คำขอจบ/สถานะการพิจารณา | ผูกผู้เรียนกับผู้อนุมัติและเวลา |
| `transfer_permissions` | ใครได้รับสิทธิ์ตรวจคำขอเทียบโอน | ใช้บังคับขอบเขตผู้ตรวจ |

### ตัวอย่างเส้นทางข้อมูลในฐาน

`users` ระบุว่าบัญชีเป็นนักเรียน → แถวใน `students` เก็บโปรไฟล์ → `student_courses` บอกว่าลงทะเบียนวิชาใด → `courses` บอกชื่อวิชา → `lessons` แสดงบท → `lesson_progress` จำตำแหน่งเรียน → `quiz_attempt_sessions` จำเวลาเริ่มสอบ → `quiz_attempts` เก็บคะแนนรอบนั้น → `quiz_attempt_answers` เก็บคำตอบแต่ละข้อ

คำศัพท์ที่ใช้ตอบ:

- **Primary key / รหัสหลัก:** รหัสประจำแถว เช่น `course_id`
- **Foreign key / รหัสอ้างอิง:** รหัสที่ชี้ไปอีกตาราง เช่น `lessons.course_id` ชี้ไปวิชา
- **JOIN:** วิธีเอาแถวที่เชื่อมกันมาดูด้วยกัน เช่น เอาชื่อวิชามาแสดงคู่กับคะแนน
- **Migration:** สคริปต์เปลี่ยนโครงสร้างฐานแบบมีลำดับ เช่น เพิ่มคอลัมน์/ดัชนี
- ไฟล์ `docs/database_schema_snapshot_20261020.md` และ audit เดือน ต.ค. เป็นเอกสาร snapshot ตามวันที่; ถ้ารายละเอียดไม่ตรงกับฐานปัจจุบัน ให้ยึด live schema ที่ตรวจล่าสุดและ migrations ตามลำดับ

## สาธิตหนึ่ง flow พร้อมชี้ไฟล์

1. เปิด `login.php` — “นี่คือแบบฟอร์มรับข้อมูล”
2. ส่งฟอร์มไป `login_action.php` — “ระบบตรวจบัญชีและรหัสผ่านกับฐาน แล้วกำหนด role/session”
3. เข้าหน้านักเรียน `student_dashboard.php` — “ไฟล์นี้วางหน้าจอ”
4. เปิด `assets/js/student_dashboard.js` และ `student_dashboard_api.php` — “JavaScript ขอข้อมูล; API ตรวจสิทธิ์และอ่านฐาน”
5. ชี้ `student_courses` → `courses` → `lessons` → `lesson_progress` — “ความสัมพันธ์บอกว่าผู้เรียนลงวิชาไหนและถึงบทไหน”
6. เปิดบทและทำแบบทดสอบ — `api_quiz.php` โหลดคำถาม, `quiz_session.php` บันทึกเริ่มสอบ, `test_submit.php` ตรวจ/บันทึกผลใน `quiz_attempts` และรายละเอียดใน `quiz_attempt_answers`
7. กลับ timeline — `learning_progress_report_api.php` รวมกิจกรรมตามเวลา, JavaScript วาดแถว และ CSS กำหนดหน้าตา

## ไฟล์ที่มีโอกาสถูกถามถึง

| ไฟล์ | คำอธิบายสำหรับพรีเซนต์ |
|---|---|
| `index.php` | จุดเข้าหน้าแรก/รายการวิชาสำหรับผู้ใช้ทั่วไปและนักเรียน |
| `login.php`, `login_action.php` | หน้าฟอร์มและ backend ตรวจบัญชี รหัสผ่าน และ role |
| `regisss.php`, `regisstu.php`, `regisss_action.php`, `regisstu_action.php` | ฟอร์มและการประมวลผลสมัครสมาชิกตามกลุ่มผู้ใช้ |
| `student_dashboard.php`, `student_dashboard_api.php`, `assets/js/student_dashboard.js` | หน้าแสดงรายวิชา ความคืบหน้า คะแนน และข้อมูลนักเรียน |
| `teacherdash.php`, `teacher_api.php`, `teacher_report.php` | งานอาจารย์: จัดการเนื้อหาที่รับผิดชอบ ติดตามและตรวจผลผู้เรียน |
| `staffdash.php`, `api_staff.php` | งานเจ้าหน้าที่: สมาชิก หลักสูตร รายวิชา และงานดูแลระบบ |
| `director_dash.php`, `export_director_report.php` | ภาพรวมและรายงานสำหรับผู้อำนวยการ |
| `parent_dashboard.php`, `parent_dashboard_api.php` | แสดงข้อมูลของนักเรียนที่เชื่อมกับบัญชีผู้ปกครอง |
| `api_quiz.php`, `quiz_session.php`, `test_submit.php` | โหลดข้อสอบ จัดการ session/เวลา attempt และรับคำตอบ/บันทึกผล |
| `learning_progress_report_api.php`, `assets/js/learning_progress_report.js`, `assets/css/learning-progress-report.css` | API, renderer และสไตล์กลางของ timeline; กรองวันนี้/3/7 วันและแบ่งหน้า 5 กิจกรรม |
| `config/db.php` | สร้าง PDO connection ไป PostgreSQL และอ่านค่าจาก environment โดยไม่เก็บรหัสผ่านไว้ใน source |
| `migrations/` | การเปลี่ยน schema แบบมีลำดับ ควรตรวจสถานะฐานข้อมูลก่อน deploy migration |
| `Dockerfile`, `composer.json` | สภาพแวดล้อม Apache/PHP และ dependency ที่ติดตั้งด้วย Composer |

## ตารางข้อมูลที่ควรรู้

- `users` เก็บบัญชีและ role; โปรไฟล์เฉพาะกลุ่มอยู่ใน `students`, `parents`, `teachers`, `staff`
- `courses`, `lessons`, `curricula`, `curriculum_courses` เก็บโครงสร้างหลักสูตรและเนื้อหา
- `student_courses`, `student_curricula` บอกการลงทะเบียนและหลักสูตรของผู้เรียน
- `lesson_progress` เก็บความคืบหน้าเอกสาร/วิดีโอ; view `lesson_progress_summary` รวมเป็นข้อมูลต่อบทเรียน
- `quiz_attempt_sessions` เก็บเวลาเริ่มและสถานะ session; `quiz_attempts` เก็บคะแนนและผลแต่ละรอบ
- งานเทียบโอน ใบรับรอง และการตรวจข้อเขียนมีตาราง/โมดูลแยกเพื่อให้ตรวจสอบย้อนหลังได้

## คำถามที่อาจถูกถาม

**ระบบป้องกันคนเห็นข้อมูลข้าม role อย่างไร?**  
ตรวจ session และ role ที่หน้าและ API แต่ละตัว ไม่พึ่งการซ่อนปุ่มบนหน้าเว็บอย่างเดียว; query บางส่วนจำกัดข้อมูลตามผู้เรียนหรืออาจารย์ที่รับผิดชอบ

**ทำไมใช้ PHP และ PostgreSQL?**  
PHP ทำหน้าเว็บและ API ใน codebase เดียว ส่วน PostgreSQL เก็บข้อมูลสัมพันธ์ เช่น บัญชี วิชา บทเรียน การลงทะเบียน และ attempt; PDO ช่วย bind parameter และจัดการ transaction

**ข้อสอบหลายรอบเก็บอย่างไร?**  
แต่ละรอบมี attempt number ของตนเองใน `quiz_attempts`; session เริ่มสอบอยู่ใน `quiz_attempt_sessions` จึงบอกได้ทั้งเริ่มเมื่อใดและส่งผลเมื่อใด

**รหัสผ่านอยู่ในฐานข้อมูลเป็นข้อความปกติไหม?**  
ไม่ควรอยู่แบบข้อความปกติ; login ตรวจ hash ด้วย `password_verify()` และค่าตั้งค่าฐานข้อมูลอ่านจาก environment ภายนอก repository

**ถ้าจะเพิ่มฟีเจอร์หรือคอลัมน์ทำอย่างไร?**  
แก้ API/หน้าเว็บตามขอบเขต แล้วเพิ่ม migration ที่ระบุลำดับและวิธี rollback/ตรวจสอบ จากนั้นทดสอบบนฐาน staging ก่อน production

**ข้อมูลหน้า timeline ต่างจากสรุปความคืบหน้าอย่างไร?**  
timeline แสดง event ตามเวลา—เข้าเรียน เริ่มสอบ และส่งสอบ—ส่วนคะแนนรวม/สถานะรายวิชาเป็นข้อมูลคนละส่วน จึงไม่ใช้จำนวน log แทนความคืบหน้าหลักสูตร

## เช็กลิสต์ก่อนเริ่มสาธิต

- ใช้บัญชี demo ที่เตรียมไว้และตรวจ role ให้ถูกต้อง; อย่าแสดงรหัสผ่านหรือข้อมูลจริงที่ระบุตัวบุคคลได้
- ตรวจว่า database/environment variables และบริการภายนอกที่จำเป็นเชื่อมต่อได้
- เปิดหน้า dashboard ของแต่ละ role ล่วงหน้า และเตรียมข้อมูลตัวอย่างอย่างน้อยหนึ่งวิชา/หนึ่งบท/หนึ่ง attempt
- สาธิต timeline ด้วยทั้งปุ่ม “วันนี้” และ “ย้อนหลัง 3 วัน”; ตรวจว่าปุ่มถัดไปแสดงกิจกรรมชุดถัดไป
- เปิด terminal เฉพาะเมื่อถูกถาม; ไม่จำเป็นต้องเล่าทุกไฟล์หรือทุกตาราง
- สถานะตรวจโค้ดรอบนี้: PHP lint ทุกไฟล์ในแอปผ่าน และ JavaScript syntax check ผ่าน; ไม่ใช่การทดสอบ end-to-end ครบทุก workflow

## สคริปต์เปิดพรี 60 วินาที

“Flexible Learning Hub เป็นระบบเรียนออนไลน์ที่เชื่อมเนื้อหาหลักสูตร การเรียน แบบทดสอบ และการติดตามผลเข้าด้วยกัน ผู้เรียนใช้เรียนและตรวจความคืบหน้า ส่วนอาจารย์ เจ้าหน้าที่ ผู้ปกครอง และผู้อำนวยการทำงานผ่านหน้าที่กำหนดตามสิทธิ์ วันนี้จะเริ่มจากหน้าสาธารณะ แล้วตามเส้นทางเข้าสู่ระบบไปยัง dashboard และปิดด้วยการอธิบายว่าข้อมูลถูกจัดเก็บใน PostgreSQL อย่างไร โดยจะใช้ข้อมูลตัวอย่างและแสดงเฉพาะภาพรวมที่ไม่ระบุตัวบุคคล”

## ถ้าหน้าเว็บหรือบริการสะดุดระหว่างพรี

1. เปิดหน้าเว็บและเข้าสู่ระบบไว้ล่วงหน้าอย่างน้อย 10 นาที; Render อาจมีช่วงเริ่มบริการช้าหลังไม่มีการใช้งาน
2. เตรียมภาพหน้าจอของหน้าแรกและ dashboard ที่ใช้สาธิตไว้ในเครื่อง แต่ปิด/เบลอข้อมูลส่วนบุคคล
3. หาก dashboard โหลดไม่ขึ้น ให้สลับไปอธิบาย flow จากไฟล์ตัวอย่าง: `login_action.php` → `student_dashboard.php` → `student_dashboard_api.php` → PostgreSQL แล้วกลับมา demo เมื่อระบบพร้อม
4. ไม่แสดง terminal ที่มี environment variables หรือผล query รายบุคคล; ข้อมูลฐานที่นำเสนอในตารางด้านบนเป็นเพียงจำนวนแถวรวม
