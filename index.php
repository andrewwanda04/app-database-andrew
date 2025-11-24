<?php
// =======================================================================
// 1. KONFIGURASI KONEKSI DATABASE (Menggunakan Environment Variables Azure)
// =======================================================================

// Ambil Environment Variables yang akan diatur di Azure App Service
$host = getenv('DB_HOST') ?: 'localhost'; // Default 'localhost' untuk testing lokal
$dbname = getenv('DB_NAME') ?: 'book_inventory';
$user = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASS') ?: '';

// Coba buat koneksi menggunakan PDO
try {
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $password);
    
    // Set Error Mode ke Exception agar mudah mendeteksi masalah
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Pastikan tabel ada (Dijalankan sekali)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS books (
            id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            author VARCHAR(255) NOT NULL,
            year INT(4) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

} catch (PDOException $e) {
    // Tampilkan pesan error jika koneksi gagal
    die("Koneksi Database Gagal: " . $e->getMessage());
}

// =======================================================================
// 2. LOGIKA CRUD
// =======================================================================

// --- READ (Membaca Semua Data) ---
$statement = $pdo->prepare("SELECT * FROM books ORDER BY id DESC");
$statement->execute();
$books = $statement->fetchAll(PDO::FETCH_ASSOC);

$error = '';
$edit_book = null; // Variabel untuk menyimpan data buku yang sedang diedit

// --- CREATE (Menambah Data) dan UPDATE (Mengubah Data) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $author = trim($_POST['author']);
    $year = (int)$_POST['year'];
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0; // Untuk operasi update

    if (empty($title) || empty($author) || empty($year)) {
        $error = "Semua field harus diisi!";
    } else {
        if ($id > 0) {
            // Logika UPDATE
            $statement = $pdo->prepare("UPDATE books SET title = ?, author = ?, year = ? WHERE id = ?");
            $statement->execute([$title, $author, $year, $id]);
        } else {
            // Logika CREATE
            $statement = $pdo->prepare("INSERT INTO books (title, author, year) VALUES (?, ?, ?)");
            $statement->execute([$title, $author, $year]);
        }
        
        // Redirect setelah operasi agar tidak terjadi double submit
        header('Location: index.php');
        exit;
    }
}

// --- DELETE (Menghapus Data) ---
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $statement = $pdo->prepare("DELETE FROM books WHERE id = ?");
    $statement->execute([$id]);

    header('Location: index.php');
    exit;
}

// --- LOAD DATA FOR EDIT (Memuat Data ke Form) ---
if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $statement = $pdo->prepare("SELECT * FROM books WHERE id = ?");
    $statement->execute([$id]);
    $edit_book = $statement->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Aplikasi CRUD Buku Azure PHP</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 800px; margin: auto; }
        h2 { border-bottom: 2px solid #ccc; padding-bottom: 5px; }
        .error { color: red; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input[type="text"], .form-group input[type="number"] { width: 90%; padding: 8px; }
        .btn { padding: 8px 15px; border: none; cursor: pointer; border-radius: 4px; color: white; }
        .btn-primary { background-color: #007bff; }
        .btn-success { background-color: #28a745; }
        .btn-danger { background-color: #dc3545; }
        .btn-warning { background-color: #ffc107; color: black; }
    </style>
</head>
<body>

<div class="container">
    <h1>📚 CRUD Inventaris Buku Sederhana</h1>
    <p>Aplikasi ini akan terhubung ke **Azure Database for MySQL** menggunakan **Environment Variables**.</p>
    
    <hr>
    
    <h2><?php echo $edit_book ? '✏️ Edit Data Buku' : '➕ Tambah Data Buku'; ?></h2>
    
    <?php if ($error): ?>
        <p class="error">⚠️ <?php echo $error; ?></p>
    <?php endif; ?>

    <form method="POST" action="index.php">
        <input type="hidden" name="id" value="<?php echo $edit_book['id'] ?? ''; ?>">
        
        <div class="form-group">
            <label for="title">Judul Buku:</label>