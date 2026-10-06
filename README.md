# Lecture Storage Telegram Bot | بوت تخزين المحاضرات

Bilingual documentation | توثيق ثنائي اللغة (English / العربية)

> Single-file Telegram bot for organizing, storing, and distributing lecture materials with folder hierarchy, role-based access, and secure file hosting via a dedicated storage channel.
>
> بوت تيليجرام بملف واحد لتنظيم وتخزين وتوزيع المحاضرات مع مجلدات هرمية وصلاحيات و تخزين آمن عبر قناة تخزين مخصصة.

File: `lecture.php` | Version: 1.0.0 | License: MIT | Author: Abdullah Al-Ajrad

---

## English

### 1. Features

**Student:**
- Browse folder tree from `/start`
- Open folders `l_open_<id>`, back `l_back`, list `l_show`
- Download files `l_download_<id>` (single or multi-file button, all files sent one by one)
- Correct Telegram method per type: photo via `sendPhoto`, video via `sendVideo`, audio via `sendAudio`, else `sendDocument`

**Admin:**
- Dashboard `l_admin_panel` with root tree + management controls
- Add folder (name prompt) / add file (name then upload PDF, video, photo, audio)
- Multi-file support: keep uploading to same button, finish with `l_admin_finish_files`
- Edit name, edit caption, manage files (add more / delete single by index), delete button/folder
- Recursive operations work at any depth via `findFolderChildrenRef()` and `findParentId()`

**Owner (ID `Owner Id`, constant `OWNER_ID`):**
- `l_owner_panel`: view admins + storage channel
- `l_owner_add_admin` / `l_owner_remove_<id>`: manage `lecture_settings.json:admins`
- `l_owner_set_storage`: forward any message from target channel, bot saves `forward_from_chat->id` as `storage_channel`

**System:**
- Anti-spam rate limit: 5 seconds for non-admins (`LECTURE_FLOOD_SECONDS`)
  - Callback spam -> `answerCallbackQuery` popup: wait N seconds
  - Text spam -> `sendmsg` warning
  - Admins exempt so bulk uploads are not blocked
- Storage pipeline: upload -> `storeLectureFile()` to storage channel with matching `send*` method -> save new `file_id` -> student delivery via `sendLectureFile()`
- JSON persistence in `storage/`:
  - `lecture_buttons.json`: `{buttons: {id: {name, type: folder|file, children, files: [{file_id, mime}], file_id (legacy), caption}}}`
  - `lecture_settings.json`: `{admins: [], storage_channel: null, owner_id}`
  - `lecture_admin.json`: `{mode: {chat_id: mode}, nav: {chat_id: root|folderId}, temp: {...}, flood: {chat_id: timestamp}}`

### 2. Requirements

- PHP 8.0+ with `allow_url_fopen=1` (uses `file_get_contents` for Bot API)
- HTTPS server with domain for webhook
- Telegram bot token from @BotFather
- Storage channel where bot is admin
- Writable `storage/` directory

### 3. Installation

1. Upload `lecture.php` to your server, e.g. `https://example.com/lecture.php`
2. Set webhook:
   ```
   https://api.telegram.org/bot<TOKEN>/setWebhook?url=https://example.com/lecture.php
   ```
3. Open bot in Telegram, send `/start` as owner to auto-create:
   - `storage/lecture_settings.json`
   - `storage/lecture_admin.json`
   - `storage/lecture_buttons.json`
4. As owner: open `Owner Panel` -> `Set Storage Channel` -> forward any message from your storage channel to the bot
5. As owner: `Add Admin` -> send admin Telegram numeric ID
6. As admin: `/start` -> `Manage Lectures` -> `Add` -> create folders/files

### 4. Configuration

Top of `lecture.php`:
```php
$token = "Enter Your Telegram Token here";
define("OWNER_ID", "Owner Id");
define("LECTURE_FLOOD_SECONDS", 5);
```

Recommended: move token to environment variable and revoke any token committed to git:
```php
$token = getenv("LECTURE_BOT_TOKEN");
```

### 5. Callback Routing

| Prefix | Purpose |
|---|---|
| `l_open_` | Student open folder |
| `l_back` | Student back |
| `l_download_` | Student download file |
| `l_show` | Student list root |
| `l_admin_panel` | Admin dashboard |
| `l_admin_open_` | Admin open folder |
| `l_admin_back` | Admin back |
| `l_admin_add` / `l_admin_add_folder` / `l_admin_add_file` | Create |
| `l_admin_edit_` / `l_admin_edit_name` / `l_admin_edit_caption` | Edit |
| `l_admin_manage_files` / `l_admin_add_more_files` / `l_admin_delete_file_` | Multi-file manage |
| `l_admin_delete_` / `l_admin_delete_from_edit_` | Delete |
| `l_admin_finish_files` | Finish upload session |
| `l_owner_panel` / `l_owner_add_admin` / `l_owner_remove_` / `l_owner_set_storage` | Owner only |

Modes (`lecture[mode][chat_id]`): `l_wait_folder_name`, `l_wait_file_name`, `l_wait_file_upload`, `l_wait_file_more`, `l_wait_edit_name`, `l_wait_edit_caption`, `l_owner_wait_admin_id`, `l_owner_wait_storage_forward`.

### 6. Security Notes

- Token is currently hardcoded. Move to env and rotate it.
- `isAdmin()` trusts `settings.json:admins`. Only owner can edit via bot.
- Add `LOCK_EX` to `file_put_contents` or migrate to SQLite for concurrency under load.
- Consider webhook secret token validation.

---

## العربية

### 1. المميزات

**طالب:**
- تصفح المجلدات من `/start`
- فتح مجلد `l_open_`، رجوع `l_back`، عرض `l_show`
- تحميل ملف `l_download_` (زر بملف واحد أو عدة ملفات ترسل واحدا تلو الآخر)
- إرسال بالنوع الصحيح: الصور `sendPhoto`، الفيديو `sendVideo`، الصوت `sendAudio`، والباقي `sendDocument`

**ادمن:**
- لوحة `l_admin_panel` تعرض الشجرة مع التحكم الكامل
- إضافة مجلد (يطلب الاسم) / إضافة ملف (يطلب الاسم ثم رفع PDF أو فيديو أو صورة أو صوت)
- دعم عدة ملفات لنفس الزر مع زر إنهاء `l_admin_finish_files`
- تعديل الاسم والوصف، إدارة الملفات (إضافة / حذف ملف واحد)، حذف زر / مجلد
- العمليات تدعم أي عمق عبر `findFolderChildrenRef()` و `findParentId()`

**المالك:**
- `l_owner_panel`: عرض الادمنية وقناة التخزين
- إضافة / حذف ادمن عبر الايدي الرقمي
- تعيين قناة التخزين عبر توجيه رسالة من القناة للبوت (يخزن `forward_from_chat->id`)

**النظام:**
- منع السبام: 5 ثواني لغير الادمنية
  - ضغط متكرر -> تنبيه `answerCallbackQuery`: انتظر N ثواني
  - نص متكرر -> رسالة `sendmsg` تحذيرية
  - الادمنية مستثناة حتى لا يتعطل الرفع الجماعي
- مسار التخزين: رفع -> `storeLectureFile()` لقناة التخزين بنفس نوع الإرسال -> حفظ `file_id` الجديد -> إرسال للطالب عبر `sendLectureFile()`

### 2. المتطلبات

- PHP 8.0+ مع `allow_url_fopen=1`
- سيرفر HTTPS بدومين للويب هوك
- توكن بوت من @BotFather
- قناة تخزين والبوت ادمن فيها
- مجلد `storage/` قابل للكتابة

### 3. التثبيت

1. ارفع `lecture.php` مثلا `https://example.com/lecture.php`
2. فعّل الويب هوك:
   ```
   https://api.telegram.org/bot<TOKEN>/setWebhook?url=https://example.com/lecture.php
   ```
3. ارسل `/start` كمالك وسيتم إنشاء ملفات `storage/` تلقائيا
4. كمالك: لوحة المالك -> تعيين قناة تخزين -> وجّه أي رسالة من قناة التخزين للبوت
5. كمالك: إضافة ادمن -> ارسل الايدي الرقمي
6. كادمن: `/start` -> إدارة المحاضرات -> إضافة -> أنشئ مجلدات وملفات

### 4. الإعداد

أعلى `lecture.php`:
```php
$token = "Enter Your Telegram Token here";
define("OWNER_ID", "Owner Id");
define("LECTURE_FLOOD_SECONDS", 5);
```

ينصح بنقل التوكن لمتغير بيئة وإبطال أي توكن منشور:
```php
$token = getenv("LECTURE_BOT_TOKEN");
```

### 5. ملفات التخزين

- `lecture_buttons.json`: الشجرة `{buttons: {id: {name, type, children, files, caption}}}`
- `lecture_settings.json`: `{admins, storage_channel, owner_id}`
- `lecture_admin.json`: `{mode, nav, temp, flood}`

الحقول `mode` تتحكم بخطوة الادمن الحالية، و `nav` بالمجلد المفتوح، و `temp` بالبيانات المؤقتة (اسم الملف، زر التعديل)، و `flood` بآخر طلب لمنع السبام.

### 6. ملاحظات أمنية

- التوكن مكتوب داخل الكود حاليا. انقله لمتغير بيئة واصدر توكن جديد.
- استخدم `LOCK_EX` عند الكتابة أو انتقل ل SQLite عند الضغط العالي.
- فعّل التحقق من Webhook secret.

---

## Developer | المطور

- Author: Abdullah Al-Ajrad
- Email: abodabdu799@gmail.com
- GitHub: https://github.com/aboodalajrad8
- License: MIT
