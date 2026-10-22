# Flexible Learning Hub

เว็บแอปจัดการการเรียนออนไลน์สำหรับนักเรียน ผู้ปกครอง อาจารย์ เจ้าหน้าที่ และผู้อำนวยการ พัฒนาด้วย PHP, PostgreSQL, HTML, CSS และ JavaScript

## ภาพรวมระบบ

- **นักเรียน:** ดูรายวิชาและบทเรียน ทำแบบทดสอบ ติดตามความคืบหน้า และส่งคำขอด้านการเรียน
- **ผู้ปกครอง:** ดูข้อมูลความคืบหน้าและผลการเรียนของนักเรียนที่เชื่อมโยง
- **อาจารย์:** จัดการเนื้อหาวิชา ตรวจงานและข้อสอบ และติดตามนักเรียน
- **เจ้าหน้าที่/ผู้อำนวยการ:** จัดการผู้ใช้ หลักสูตร คำขอ และรายงาน

## โครงสร้างโค้ด

| ตำแหน่ง | หน้าที่ |
|---|---|
| `index.php`, `login.php`, `regisstu.php` | หน้าหลัก เข้าสู่ระบบ และสมัครสมาชิก |
| `staffdash.php`, `teacherdash.php`, `student_dashboard.php`, `parent_dashboard.php`, `director_dash.php` | หน้าหลักตามบทบาท |
| `api_*.php`, `*_api.php`, `*_action.php` | API และการรับคำสั่งจากแบบฟอร์ม |
| `assets/css/`, `assets/js/` | สไตล์และ JavaScript ฝั่งเว็บ |
| `components/`, `includes/` | ส่วนประกอบและโค้ดที่ใช้ร่วมกัน |
| `config/db.php` | การเชื่อมต่อ PostgreSQL ด้วย PDO และค่าตั้งค่าจาก environment |
| `migrations/` | SQL สำหรับเปลี่ยน schema และ rollback |
| `docs/` | ภาพรวม schema และบันทึกการตรวจ/ปรับฐานข้อมูล |

## ฐานข้อมูล

ระบบเชื่อมต่อ PostgreSQL schema `public` ปัจจุบันเหลือ 25 ตารางที่ใช้งาน หลังนำตารางบันทึกข้อสอบเก่า `exam_logs` ออก ดูผลตรวจที่ [รายงานตรวจตาราง](docs/database_table_audit_20261022.md) และบันทึกการ cleanup ที่ [เอกสาร cleanup](docs/database_cleanup_20261022.md)

## การตั้งค่าและรัน

- ตั้งค่าการเชื่อมต่อฐานข้อมูลและบริการภายนอกผ่าน environment variables ตามที่ `config/db.php` อ่าน
- ติดตั้ง PHP dependencies ด้วย Composer (`composer install`)
- สำหรับ Docker ใช้ `Dockerfile` ที่เตรียม Apache และ PostgreSQL PDO driver ไว้แล้ว
- ห้าม commit ค่า secret หรือไฟล์ environment ที่มี credentials
