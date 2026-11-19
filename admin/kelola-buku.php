<?php
require_once '../config/database.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

$message = '';
$error = '';

// Handle tambah/edit buku
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'add' || $action === 'edit') {
        $judul = trim($_POST['judul']);
        $subjudul = trim($_POST['subjudul']);
        $tahun_terbit = $_POST['tahun_terbit'];
        $isbn = trim($_POST['isbn']);
        $jenis_koleksi = $_POST['jenis_koleksi'];
        $id_kategori = $_POST['id_kategori'];
        $cover_image = '';
        
        // Handle upload cover
        if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['cover_image']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed)) {
                $newname = uniqid() . '_' . time() . '.' . $ext;
                $destination = '../uploads/covers/' . $newname;
                
                if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $destination)) {
                    $cover_image = $newname;
                }
            }
        }
        
        if (empty($judul)) {
            $error = 'Judul buku wajib diisi';
        } else {
            try {
                if ($action === 'add') {
                    query(
                        "INSERT INTO koleksi (judul, subjudul, tahun_terbit, isbn, jenis_koleksi, id_kategori, cover_image) 
                         VALUES (?, ?, ?, ?, ?, ?, ?)",
                        [$judul, $subjudul, $tahun_terbit, $isbn, $jenis_koleksi, $id_kategori ?: null, $cover_image ?: null]
                    );
                    $message = 'Buku berhasil ditambahkan';
                } else {
                    $id_koleksi = $_POST['id_koleksi'];
                    
                    if ($cover_image) {
                        // Hapus cover lama jika ada
                        $old = query("SELECT cover_image FROM koleksi WHERE id_koleksi = ?", [$id_koleksi])->fetch();
                        if ($old && $old['cover_image'] && file_exists('../uploads/covers/' . $old['cover_image'])) {
                            unlink('../uploads/covers/' . $old['cover_image']);
                        }
                        
                        query(
                            "UPDATE koleksi SET judul = ?, subjudul = ?, tahun_terbit = ?, isbn = ?, jenis_koleksi = ?, id_kategori = ?, cover_image = ? 
                             WHERE id_koleksi = ?",
                            [$judul, $subjudul, $tahun_terbit, $isbn, $jenis_koleksi, $id_kategori ?: null, $cover_image, $id_koleksi]
                        );
                    } else {
                        query(
                            "UPDATE koleksi SET judul = ?, subjudul = ?, tahun_terbit = ?, isbn = ?, jenis_koleksi = ?, id_kategori = ? 
                             WHERE id_koleksi = ?",
                            [$judul, $subjudul, $tahun_terbit, $isbn, $jenis_koleksi, $id_kategori ?: null, $id_koleksi]
                        );
                    }
                    $message = 'Buku berhasil diperbarui';
                }
            } catch (Exception $e) {
                $error = 'Gagal menyimpan buku: ' . $e->getMessage();
            }
        }
    }
    
    // Tambah eksemplar
    if ($action === 'add_eksemplar') {
        $id_koleksi = $_POST['id_koleksi'];
        $jumlah = (int)$_POST['jumlah'];
        
        try {
            for ($i = 0; $i < $jumlah; $i++) {
                $kode_barcode = 'BC' . str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);
                $nomor_akses = 'ACC-' . str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);
                
                query(
                    "INSERT INTO eksemplar (id_koleksi, id_perpustakaan, kode_barcode, nomor_akses, status) 
                     VALUES (?, 1, ?, ?, 'AVAILABLE')",
                    [$id_koleksi, $kode_barcode, $nomor_akses]
                );
            }
            $message = "$jumlah eksemplar berhasil ditambahkan";
        } catch (Exception $e) {
            $error = 'Gagal menambah eksemplar: ' . $e->getMessage();
        }
    }
    
    // Hapus buku
    if ($action === 'delete') {
        $id_koleksi = $_POST['id_koleksi'];
        
        try {
            query("DELETE FROM koleksi WHERE id_koleksi = ?", [$id_koleksi]);
            $message = 'Buku berhasil dihapus';
        } catch (Exception $e) {
            $error = 'Gagal menghapus buku: ' . $e->getMessage();
        }
    }
}

// Ambil semua buku
$books = query(
    "SELECT 
        k.id_koleksi,
        k.judul,
        k.subjudul,
        k.tahun_terbit,
        k.isbn,
        k.jenis_koleksi,
        k.cover_image,
        kat.nama as kategori,
        COUNT(DISTINCT e.id_eksemplar) as total_eksemplar,
        COUNT(DISTINCT CASE WHEN e.status = 'AVAILABLE' THEN e.id_eksemplar END) as tersedia
     FROM koleksi k
     LEFT JOIN kategori kat ON k.id_kategori = kat.id_kategori
     LEFT JOIN eksemplar e ON k.id_koleksi = e.id_koleksi
     GROUP BY k.id_koleksi
     ORDER BY k.dibuat_pada DESC"
)->fetchAll();

// Ambil kategori untuk dropdown
$categories = query("SELECT * FROM kategori ORDER BY nama")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Buku - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="container">
            <a href="../index.php" class="navbar-brand">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                    <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                </svg>
                <span class="navbar-title">Admin Panel</span>
            </a>
            <ul class="navbar-menu">
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="kelola-buku.php">Kelola Buku</a></li>
                <li><a href="kelola-anggota.php">Kelola Anggota</a></li>
                <li><a href="../logout.php" class="btn btn-secondary">Keluar</a></li>
            </ul>
        </div>
    </nav>

    <div class="dashboard">
        <div class="container-fluid">
            <div class="dashboard-header">
                <div class="dashboard-welcome">
                    <div class="dashboard-title">
                        <h1>Kelola Buku</h1>
                        <p>Tambah, edit, atau hapus buku dari koleksi</p>
                    </div>
                    <button class="btn btn-primary" data-modal="modalTambahBuku">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 5v14"></path>
                            <path d="M5 12h14"></path>
                        </svg>
                        Tambah Buku Baru
                    </button>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="15" y1="9" x2="9" y2="15"></line>
                        <line x1="9" y1="9" x2="15" y2="15"></line>
                    </svg>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="table-container">
                <div class="table-header">
                    <h3>Daftar Buku (<?php echo count($books); ?>)</h3>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Cover</th>
                                <th>Judul</th>
                                <th>Kategori</th>
                                <th>Tahun</th>
                                <th>Jenis</th>
                                <th>Total Eksemplar</th>
                                <th>Tersedia</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($books as $book): ?>
                                <tr>
                                    <td>
                                        <?php if ($book['cover_image'] && file_exists('../uploads/covers/' . $book['cover_image'])): ?>
                                            <img src="../uploads/covers/<?php echo htmlspecialchars($book['cover_image']); ?>" 
                                                 alt="<?php echo htmlspecialchars($book['judul']); ?>" 
                                                 class="book-thumbnail">
                                        <?php else: ?>
                                            <div class="book-thumbnail-placeholder">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                                                    <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                                                </svg>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($book['judul']); ?></strong>
                                        <?php if ($book['subjudul']): ?>
                                            <br><small style="color: var(--text-muted);"><?php echo htmlspecialchars($book['subjudul']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($book['kategori'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($book['tahun_terbit'] ?? '-'); ?></td>
                                    <td><span class="badge badge-info"><?php echo htmlspecialchars($book['jenis_koleksi']); ?></span></td>
                                    <td><?php echo $book['total_eksemplar']; ?></td>
                                    <td>
                                        <?php if ($book['tersedia'] == 0): ?>
                                            <span class="badge badge-error"><?php echo $book['tersedia']; ?></span>
                                        <?php elseif ($book['tersedia'] <= 2): ?>
                                            <span class="badge badge-warning"><?php echo $book['tersedia']; ?></span>
                                        <?php else: ?>
                                            <span class="badge badge-success"><?php echo $book['tersedia']; ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn btn-sm btn-secondary" onclick="tambahEksemplar(<?php echo $book['id_koleksi']; ?>, '<?php echo htmlspecialchars($book['judul']); ?>')" title="Tambah Eksemplar">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M12 5v14"></path>
                                                    <path d="M5 12h14"></path>
                                                </svg>
                                            </button>
                                            <form method="POST" style="display: inline; margin: 0;" onsubmit="return confirm('Yakin ingin menghapus buku ini?')">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id_koleksi" value="<?php echo $book['id_koleksi']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" title="Hapus Buku">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                        <polyline points="3 6 5 6 21 6"></polyline>
                                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                                    </svg>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Tambah Buku -->
    <div id="modalTambahBuku" class="modal">
        <div class="modal-content modal-wide">
            <div class="modal-header">
                <h3 class="modal-title">Tambah Buku Baru</h3>
                <button class="modal-close">&times;</button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="form-group">
                        <label class="form-label">Cover Buku (Opsional)</label>
                        <input type="file" name="cover_image" class="form-input" accept="image/*" onchange="previewImage(this)">
                        <small style="color: var(--text-muted);">Format: JPG, PNG, GIF. Max: 2MB</small>
                        <div id="imagePreview" class="upload-preview" style="display: none;">
                            <img id="previewImg" src="" alt="Preview">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Judul Buku</label>
                        <input type="text" name="judul" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Subjudul (Opsional)</label>
                        <input type="text" name="subjudul" class="form-input">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">ISBN</label>
                        <input type="text" name="isbn" class="form-input">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Tahun Terbit</label>
                        <input type="number" name="tahun_terbit" class="form-input" min="1000" max="9999">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Jenis Koleksi</label>
                        <select name="jenis_koleksi" class="form-select" required>
                            <option value="BOOK">Buku</option>
                            <option value="JOURNAL">Jurnal</option>
                            <option value="MULTIMEDIA">Multimedia</option>
                            <option value="THESIS">Skripsi/Tesis</option>
                            <option value="REPORT">Laporan</option>
                            <option value="DIGITAL">Digital</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Kategori</label>
                        <select name="id_kategori" class="form-select">
                            <option value="">Pilih kategori</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id_kategori']; ?>">
                                    <?php echo htmlspecialchars($cat['nama']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Buku</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Tambah Eksemplar -->
    <div id="modalTambahEksemplar" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Tambah Eksemplar</h3>
                <button class="modal-close">&times;</button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_eksemplar">
                    <input type="hidden" name="id_koleksi" id="eksemplar_id_koleksi">
                    
                    <p id="eksemplar_buku_title" style="margin-bottom: 1.5rem; color: var(--text-secondary);"></p>
                    
                    <div class="form-group">
                        <label class="form-label">Jumlah Eksemplar yang Ditambahkan</label>
                        <input type="number" name="jumlah" class="form-input" min="1" max="100" value="1" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Tambah Eksemplar</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script>
        function tambahEksemplar(id, judul) {
            document.getElementById('eksemplar_id_koleksi').value = id;
            document.getElementById('eksemplar_buku_title').textContent = 'Buku: ' + judul;
            openModal('modalTambahEksemplar');
        }
        
        function previewImage(input) {
            const preview = document.getElementById('imagePreview');
            const previewImg = document.getElementById('previewImg');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    preview.style.display = 'block';
                }
                
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.style.display = 'none';
            }
        }
    </script>
</body>
</html>
