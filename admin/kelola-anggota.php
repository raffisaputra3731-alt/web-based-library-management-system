<?php
require_once '../config/database.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

$message = '';
$error = '';

// Handle aksi anggota
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    // Ubah status anggota
    if ($action === 'change_status') {
        $id_anggota = $_POST['id_anggota'];
        $status = $_POST['status'];
        
        try {
            query(
                "UPDATE anggota SET status_keanggotaan = ? WHERE id_anggota = ?",
                [$status, $id_anggota]
            );
            $message = 'Status anggota berhasil diubah';
        } catch (Exception $e) {
            $error = 'Gagal mengubah status: ' . $e->getMessage();
        }
    }
    
    // Hapus anggota
    if ($action === 'delete') {
        $id_anggota = $_POST['id_anggota'];
        
        // Cek apakah masih ada peminjaman aktif
        $aktif = query(
            "SELECT COUNT(*) as total FROM peminjaman WHERE id_anggota = ? AND status = 'ACTIVE'",
            [$id_anggota]
        )->fetch()['total'];
        
        if ($aktif > 0) {
            $error = 'Tidak dapat menghapus anggota yang masih memiliki peminjaman aktif';
        } else {
            try {
                query("DELETE FROM anggota WHERE id_anggota = ?", [$id_anggota]);
                $message = 'Anggota berhasil dihapus';
            } catch (Exception $e) {
                $error = 'Gagal menghapus anggota: ' . $e->getMessage();
            }
        }
    }
}

// Ambil semua anggota
$anggota = query(
    "SELECT 
        a.id_anggota,
        a.nik,
        a.nama_lengkap,
        a.email,
        a.telepon,
        a.status_keanggotaan,
        a.terdaftar_pada,
        COUNT(DISTINCT CASE WHEN p.status = 'ACTIVE' THEN p.id_peminjaman END) as sedang_pinjam,
        COUNT(DISTINCT CASE WHEN p.status = 'RETURNED' THEN p.id_peminjaman END) as total_pinjam,
        COALESCE(SUM(p.jumlah_denda), 0) as total_denda
     FROM anggota a
     LEFT JOIN peminjaman p ON a.id_anggota = p.id_anggota
     GROUP BY a.id_anggota
     ORDER BY a.terdaftar_pada DESC"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Anggota - Admin</title>
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
                        <h1>Kelola Anggota</h1>
                        <p>Kelola data dan status anggota perpustakaan</p>
                    </div>
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

            <!-- Tabel Anggota -->
            <div class="table-container">
                <div class="table-header">
                    <h3>Daftar Anggota (<?php echo count($anggota); ?>)</h3>
                    <input type="text" id="searchInput" class="search-input" placeholder="Cari anggota..." style="max-width: 300px;">
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>Email</th>
                                <th>Telepon</th>
                                <th>Terdaftar</th>
                                <th>Sedang Pinjam</th>
                                <th>Total Denda</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="anggotaTableBody">
                            <?php foreach ($anggota as $item): ?>
                                <tr class="anggota-row" data-search="<?php echo strtolower($item['nama_lengkap'] . ' ' . $item['email']); ?>">
                                    <td>
                                        <strong><?php echo htmlspecialchars($item['nama_lengkap']); ?></strong>
                                        <br><small style="color: var(--text-muted);">NIK: <?php echo htmlspecialchars($item['nik']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($item['email']); ?></td>
                                    <td><?php echo htmlspecialchars($item['telepon'] ?? '-'); ?></td>
                                    <td><?php echo date('d M Y', strtotime($item['terdaftar_pada'])); ?></td>
                                    <td>
                                        <?php if ($item['sedang_pinjam'] > 0): ?>
                                            <span class="badge badge-warning"><?php echo $item['sedang_pinjam']; ?> buku</span>
                                        <?php else: ?>
                                            <span class="badge badge-success">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($item['total_denda'] > 0): ?>
                                            <span class="badge badge-error">Rp <?php echo number_format($item['total_denda'], 0, ',', '.'); ?></span>
                                        <?php else: ?>
                                            <span class="badge badge-success">Tidak ada</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $statusClass = 'badge-success';
                                        if ($item['status_keanggotaan'] === 'SUSPENDED') $statusClass = 'badge-warning';
                                        if ($item['status_keanggotaan'] === 'BANNED') $statusClass = 'badge-error';
                                        if ($item['status_keanggotaan'] === 'EXPIRED') $statusClass = 'badge-info';
                                        ?>
                                        <span class="badge <?php echo $statusClass; ?>"><?php echo $item['status_keanggotaan']; ?></span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn btn-sm btn-secondary" onclick="ubahStatus(<?php echo $item['id_anggota']; ?>, '<?php echo htmlspecialchars($item['nama_lengkap']); ?>', '<?php echo $item['status_keanggotaan']; ?>')" title="Ubah Status">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                                </svg>
                                            </button>
                                            <?php if ($item['sedang_pinjam'] == 0): ?>
                                                <form method="POST" style="display: inline; margin: 0;" onsubmit="return confirm('Yakin ingin menghapus anggota ini?')">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id_anggota" value="<?php echo $item['id_anggota']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger" title="Hapus Anggota">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                            <polyline points="3 6 5 6 21 6"></polyline>
                                                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                                        </svg>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
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

    <!-- Modal Ubah Status -->
    <div id="modalUbahStatus" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Ubah Status Anggota</h3>
                <button class="modal-close">&times;</button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="change_status">
                    <input type="hidden" name="id_anggota" id="status_id_anggota">
                    
                    <p id="status_anggota_name" style="margin-bottom: 1.5rem; color: var(--text-secondary);"></p>
                    
                    <div class="form-group">
                        <label class="form-label">Status Keanggotaan</label>
                        <select name="status" id="status_select" class="form-select" required>
                            <option value="ACTIVE">Aktif</option>
                            <option value="SUSPENDED">Suspended</option>
                            <option value="EXPIRED">Expired</option>
                            <option value="BANNED">Banned</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script>
        function ubahStatus(id, nama, currentStatus) {
            document.getElementById('status_id_anggota').value = id;
            document.getElementById('status_anggota_name').textContent = 'Anggota: ' + nama;
            document.getElementById('status_select').value = currentStatus;
            openModal('modalUbahStatus');
        }
        
        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('.anggota-row');
            
            rows.forEach(row => {
                const searchData = row.getAttribute('data-search');
                if (searchData.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>
