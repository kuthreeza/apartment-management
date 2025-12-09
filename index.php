<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบบริหารอพาร์ทเม้นท์ - (รวม)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; height: 100vh; display: flex; align-items: center; justify-content: center; }
        .card-box { cursor: pointer; transition: 0.3s; }
        .card-box:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <div class="container text-center">
        <h2 class="mb-5">🏢 เลือกอพาร์ทเม้นท์ที่ต้องการจัดการ</h2>
        <div class="row justify-content-center g-4">
            
            <div class="col-md-5">
                <a href="dorm_management/test_login.html" class="text-decoration-none">
                    <div class="card card-box border-primary h-100 p-4">
                        <div class="card-body">
                            <h3 class="text-primary">🏠 อพาร์ทเม้นท์แม่น้อย</h3>
                            <p class="text-muted">ระบบจัดการหอพักฝั่งครอบครัว</p>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-md-5">
                <a href="dorm_management_aunt/test_login.html" class="text-decoration-none">
                    <div class="card card-box border-success h-100 p-4">
                        <div class="card-body">
                            <h3 class="text-success">🏩 อพาร์ทเม้นท์ป้าปิ่น</h3>
                            <p class="text-muted">ระบบจัดการหอพักฝั่งคุณน้า</p>
                        </div>
                    </div>
                </a>
            </div>

        </div>
    </div>
</body>
</html>