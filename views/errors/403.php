<?php
$pageTitle = 'Akses Ditolak (403 Forbidden)';
http_response_code(403);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 - Akses Ditolak</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center min-vh-100">
    <div class="container text-center py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow-lg border-0 p-4 rounded-4">
                    <div class="card-body">
                        <i class="bi bi-shield-slash-fill text-danger display-1 mb-3"></i>
                        <h1 class="h2 fw-bold text-dark">403 - Akses Ditolak</h1>
                        <p class="text-secondary lead mt-3">
                            Maaf, Anda tidak memiliki izin otorisasi untuk mengakses halaman atau fitur ini.
                        </p>
                        <hr class="my-4">
                        <div class="d-flex justify-content-center gap-2">
                            <a href="index.php" class="btn btn-primary px-4">
                                <i class="bi bi-house-door me-1"></i> Kembali ke Dashboard
                            </a>
                            <a href="logout.php" class="btn btn-outline-danger px-3">
                                <i class="bi bi-box-arrow-right me-1"></i> Ganti Akun
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
