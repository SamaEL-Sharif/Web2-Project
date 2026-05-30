<?php
// ==========================================
// الإعدادات وقاعدة البيانات العامة للحفظ
// ==========================================

$USE_MYSQL = true; // تحويل إلى true لتفعيل الـ MySQL 

$DB_HOST = 'localhost';
$DB_NAME = 'news_management_db';
$DB_USER = 'root'; 
$DB_PASS = '';     

// بدء الجلسة لإدارة الصلاحيات
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// الاتصال بقاعدة البيانات باستخدام PDO
try {
    if ($USE_MYSQL) {
        $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    } else {
        $pdo = new PDO("sqlite:news_db.sqlite");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        
        // بناء الجداول الافتراضية لـ SQLite إن لم تكن موجودة
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY AUTO_INCREMENT, name TEXT, email TEXT UNIQUE, password TEXT)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS categories (id INTEGER PRIMARY KEY AUTO_INCREMENT, name TEXT UNIQUE)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS news (id INTEGER PRIMARY KEY AUTO_INCREMENT, title TEXT, content TEXT, category_id INTEGER, image TEXT, user_id INTEGER, deleted INTEGER DEFAULT 0)");
    }
} catch (PDOException $e) {
    die("فشل الاتصال بقاعدة البيانات: " . $e->getMessage());
}

// مصفوفات جلب البيانات العامة للوحة
$categories = $pdo->query("SELECT * FROM categories")->fetchAll();
$users = $pdo->query("SELECT * FROM users")->fetchAll();

// جلب الأخبار النشطة (غير المحذوفة منطقياً)
$news_stmt = $pdo->query("SELECT news.*, categories.name AS category_name, users.name AS author_name 
                          FROM news 
                          LEFT JOIN categories ON news.category_id = categories.id 
                          LEFT JOIN users ON news.user_id = users.id 
                          WHERE news.deleted = 0 
                          ORDER BY news.id DESC");
$news_list = $news_stmt->fetchAll();

// منطق الجلسات والتحقق الحالي
$is_logged_in = isset($_SESSION['user_id']);
$current_user = $is_logged_in ? $_SESSION['user_name'] : null;
$current_user_id = $is_logged_in ? $_SESSION['user_id'] : null;
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'login';

// معالجة الأوامر البرمجية (Actions)
$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['login_email'];
    $password = $_POST['login_password'];
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user && $user['password'] === $password) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        header("Location: index.php");
        exit;
    } else {
        echo "<script>alert('خطأ في البريد الإلكتروني أو كلمة المرور'); window.location.href='index.php?tab=login';</script>";
        exit;
    }
}

if ($action === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['register_name'];
    $email = $_POST['register_email'];
    $password = $_POST['register_password'];
    
    try {
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
        $stmt->execute([$name, $email, $password]);
        
        $last_id = $pdo->lastInsertId();
        $_SESSION['user_id'] = $last_id;
        $_SESSION['user_name'] = $name;
        header("Location: index.php");
        exit;
    } catch (Exception $e) {
        echo "<script>alert('البريد الإلكتروني مسجل مسبقاً بالفعل!'); window.location.href='index.php?tab=register';</script>";
        exit;
    }
}

if ($action === 'logout') {
    session_destroy();
    header("Location: index.php");
    exit;
}

if ($action === 'add_news' && $_SERVER['REQUEST_METHOD'] === 'POST' && $is_logged_in) {
    $title = $_POST['title'];
    $content = $_POST['content'];
    $category_id = $_POST['category_id'];
    $image = $_POST['image'] ? $_POST['image'] : 'https://images.unsplash.com/photo-1504711434969-e33886168f5c?w=800';
    
    $stmt = $pdo->prepare("INSERT INTO news (title, content, category_id, image, user_id, deleted) VALUES (?, ?, ?, ?, ?, 0)");
    $stmt->execute([$title, $content, $category_id, $image, $current_user_id]);
    header("Location: index.php");
    exit;
}

if ($action === 'delete_news' && isset($_GET['id']) && $is_logged_in) {
    $id = $_GET['id'];
    $stmt = $pdo->prepare("UPDATE news SET deleted = 1 WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: index.php");
    exit;
}

if ($action === 'add_category' && $_SERVER['REQUEST_METHOD'] === 'POST' && $is_logged_in) {
    $cat_name = $_POST['cat_name'];
    try {
        $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
        $stmt->execute([$cat_name]);
    } catch (Exception $e) {}
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التحك</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
    <link class="rtl" rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Cairo', sans-serif;
            background-color: #f8fafc; 
            color: #0f172a;
        }
        
        /* الهيدر العلوي النظيف */
        .header-brand-bg {
            background-color: #ffffff; 
            border-bottom: 1px solid #e2e8f0;
        }
        
        /* كروت الإحصائيات البسيطة جداً */
        .stat-box {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 16px 20px;
        }
        
        .stat-box .num {
            font-size: 24px;
            font-weight: 700;
            color: #0f172a;
        }

        .card {
            background: #ffffff !important;
            border: 1px solid #e2e8f0 !important;
            border-radius: 4px !important;
            box-shadow: none !important;
        }
        
        /* فورم المدخلات الجادة */
        .form-control, .form-select {
            border-radius: 4px !important;
            border: 1px solid #cbd5e1 !important;
            padding: 10px 12px;
            font-size: 13.5px;
        }
        .form-control:focus, .form-select:focus {
            border-color: #0f172a !important;
            box-shadow: none !important;
        }
        
        /* الأزرار المصمتة الحادة */
        .btn {
            border-radius: 4px !important;
            font-size: 13px !important;
            font-weight: 600 !important;
        }
        .btn-dark {
            background-color: #0f172a !important;
            border-color: #0f172a !important;
        }
        
        /* الجداول الاستعراضية النظيفة */
        .table th {
            font-weight: 700;
            font-size: 13px;
            background-color: #f8fafc !important;
            color: #475569 !important;
            border-bottom: 1px solid #e2e8f0 !important;
            padding: 12px 10px !important;
        }
        .table td {
            padding: 12px 10px !important;
            font-size: 13px;
            border-bottom: 1px solid #f1f5f9 !important;
        }
        
        .news-table img {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 4px;
        }

        /* صفحة تسجيل الدخول المبسطة (Minimal Center) */
        .login-wrapper {
            min-vh: 80vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 0;
        }
        .login-box {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 32px;
            width: 100%;
            max-width: 400px;
        }
    </style>
</head>
<body>

    <header class="header-brand-bg py-3 mb-4">
        <div class="container d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
                <div class="bg-dark text-white px-2 py-0.5 rounded-1 fw-bold font-monospace" style="font-size: 13px;"></div>
                <h1 class="h6 fw-bold mb-0 text-dark">لوحة إدارة الأخبار</h1>
            </div>
            <div>
                <?php if ($is_logged_in): ?>
                    <span class="small text-muted me-3">المحرر: <strong class="text-dark"><?php echo htmlspecialchars($current_user); ?></strong></span>
                    <a href="index.php?action=logout" class="btn btn-sm btn-outline-danger px-3 py-1">خروج</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="container mb-5">
        
        <?php if (!$is_logged_in): ?>
        <div class="login-wrapper">
            <div class="login-box shadow-sm">
                
                <div class="d-flex border-bottom mb-4 justify-content-center">
                    <a href="index.php?tab=login" class="pb-2 px-3 text-decoration-none fw-bold small <?php echo $tab === 'login' ? 'border-bottom border-2 border-dark text-dark' : 'text-muted'; ?>">
                        تسجيل الدخول
                    </a>
                    <a href="index.php?tab=register" class="pb-2 px-3 text-decoration-none fw-bold small <?php echo $tab === 'register' ? 'border-bottom border-2 border-dark text-dark' : 'text-muted'; ?>">
                        إنشاء حساب
                    </a>
                </div>

                <?php if ($tab === 'login'): ?>
                <form action="index.php?action=login" method="POST">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">البريد الإلكتروني</label>
                        <input type="email" name="login_email" value="admin@news.com" class="form-control" placeholder="name@company.com" required>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-secondary">كلمة المرور</label>
                        <input type="password" name="login_password" value="123" class="form-control" placeholder="••••••••" required>
                    </div>

                    <button type="submit" class="btn btn-dark w-100 py-2 fw-bold">دخول للمنصة</button>
                </form>

                <?php elseif ($tab === 'register'): ?>
                <form action="index.php?action=register" method="POST">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">الاسم الكامل</label>
                        <input type="text" name="register_name" class="form-control" placeholder="أحمد علي" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">البريد الإلكتروني</label>
                        <input type="email" name="register_email" class="form-control" placeholder="name@company.com" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold text-secondary">كلمة المرور</label>
                        <input type="password" name="register_password" class="form-control" placeholder="••••••••" required>
                    </div>

                    <button type="submit" class="btn btn-dark w-100 py-2 fw-bold">تأكيد التسجيل</button>
                </form>
                <?php endif; ?>

            </div>
        </div>

        <?php else: ?>
        
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="stat-box d-flex align-items-center justify-content-between">
                    <span class="small fw-bold text-secondary">إجمالي الأخبار الحالية</span>
                    <span class="num"><?php echo count($news_list); ?></span>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-box d-flex align-items-center justify-content-between">
                    <span class="small fw-bold text-secondary">التصنيفات المتاحة</span>
                    <span class="num"><?php echo count($categories); ?></span>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-box d-flex align-items-center justify-content-between">
                    <span class="small fw-bold text-secondary">الكتاب والمحررين</span>
                    <span class="num"><?php echo count($users); ?></span>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-8">
                <div class="p-4 card">
                    <h5 class="fw-bold mb-3 text-dark"><i class="fa-solid fa-pen-to-square me-1"></i> كتابة ونشر خبر جديد</h5>
                    <form action="index.php?action=add_news" method="POST">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary">عنوان الخبر</label>
                            <input type="text" name="title" class="form-control" placeholder="أدخل العنوان هنا..." required>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-secondary">التصنيف</label>
                                <select name="category_id" class="form-select" required>
                                    <option value="">اختر القسم...</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-secondary">رابط صورة الغلاف (اختياري)</label>
                                <input type="url" name="image" class="form-control" placeholder="https://example.com/photo.jpg">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary">نص ومحتوى الخبر</label>
                            <textarea name="content" rows="4" class="form-control" placeholder="اكتب تفاصيل الخبر كاملة هنا..." required></textarea>
                        </div>
                        <button type="submit" class="btn btn-dark px-4 py-2">نشر فوري</button>
                    </form>
                </div>
            </div>

            <div class="col-md-4">
                <div class="p-4 card h-100 d-flex flex-column justify-content-between">
                    <div>
                        <h5 class="fw-bold mb-3 text-dark"><i class="fa-solid fa-folder-plus me-1"></i> قسم جديد</h5>
                        <form action="index.php?action=add_category" method="POST" class="mb-3">
                            <div class="input-group">
                                <input type="text" name="cat_name" class="form-control" placeholder="اسم القسم" required>
                                <button class="btn btn-dark" type="submit">إضافة</button>
                            </div>
                        </form>
                    </div>
                    <div>
                        <small class="text-muted d-block mb-2 fw-bold">الأقسام الحالية:</small>
                        <div class="d-flex flex-wrap gap-1">
                            <?php foreach ($categories as $cat): ?>
                                <span class="badge bg-light text-dark border px-2 py-1.5"><?php echo htmlspecialchars($cat['name']); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="p-4 card">
            <h5 class="fw-bold mb-3 text-dark"><i class="fa-solid fa-list-check me-1"></i> إدارة وجدول الأخبار المنشورة</h5>
            <div class="table-responsive">
                <table class="table align-middle news-table mb-0">
                    <thead>
                        <tr>
                            <th style="width: 50px;">ID</th>
                            <th style="width: 60px;">صورة</th>
                            <th>العنوان</th>
                            <th>القسم</th>
                            <th>الكاتب</th>
                            <th class="text-center" style="width: 90px;">إجراء</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($news_list) === 0): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">لا توجد أخبار منشورة حالياً في قاعدة البيانات.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($news_list as $news): ?>
                                <tr>
                                    <td class="font-monospace text-secondary">#<?php echo $news['id']; ?></td>
                                    <td><img src="<?php echo htmlspecialchars($news['image']); ?>" alt=""></td>
                                    <td class="fw-bold text-dark"><?php echo htmlspecialchars($news['title']); ?></td>
                                    <td><span class="badge bg-light text-secondary border px-2 py-1"><?php echo htmlspecialchars($news['category_name'] ? $news['category_name'] : 'عام'); ?></span></td>
                                    <td class="small text-muted"><?php echo htmlspecialchars($news['author_name'] ? $news['author_name'] : 'مشرف'); ?></td>
                                    <td class="text-center">
                                        <a href="index.php?action=delete_news&id=<?php echo $news['id']; ?>" class="btn btn-sm btn-outline-danger px-2 py-0.5" onclick="return confirm('حذف هذا الخبر؟')">حذف</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php endif; ?>
    </main>

    <footer class="text-center text-muted py-4 mt-5 border-top small">
        <div class="container">
            &copy; 2026  Systems. جميع الحقوق محفوظة.
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bundle.min.js"></script>
</body>
</html>