// =============================================
// api.php - API Endpoints
// =============================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'config.php';

$database = new Database();
$pdo = $database->connect();

$method = $_SERVER['REQUEST_METHOD'];
$request = $_SERVER['REQUEST_URI'];
$path = parse_url($request, PHP_URL_PATH);
$path = trim($path, '/');
$path_parts = explode('/', $path);

// Route handling
if ($path_parts[0] === 'api') {
    $endpoint = $path_parts[1] ?? '';
    $id = $path_parts[2] ?? null;
    
    switch ($endpoint) {
        case 'artworks':
            if ($method === 'GET') {
                if ($id) {
                    getArtworkById($pdo, $id);
                } else {
                    getAllArtworks($pdo);
                }
            }
            break;
            
        case 'order':
            if ($method === 'POST') {
                createOrder($pdo);
            }
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
    }
} else {
    http_response_code(404);
    echo json_encode(['error' => 'API not found']);
}

// =============================================
// API Functions
// =============================================

function getAllArtworks($pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM artwork_full_info WHERE status = 'available'");
        $artworks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $artworks]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function getArtworkById($pdo, $id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM artwork_full_info WHERE id = ?");
        $stmt->execute([$id]);
        $artwork = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($artwork) {
            echo json_encode(['success' => true, 'data' => $artwork]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Artwork not found']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function createOrder($pdo) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON data']);
            return;
        }
        
        $required_fields = ['customer_name', 'customer_email', 'artwork_id'];
        foreach ($required_fields as $field) {
            if (!isset($input[$field]) || empty($input[$field])) {
                http_response_code(400);
                echo json_encode(['error' => "Missing required field: $field"]);
                return;
            }
        }
        
        $stmt = $pdo->prepare("CALL CreateOrder(?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $input['customer_name'],
            $input['customer_email'],
            $input['customer_phone'] ?? '',
            $input['artwork_id'],
            $input['shipping_address'] ?? '',
            $input['notes'] ?? ''
        ]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['result'] === 'SUCCESS') {
            echo json_encode([
                'success' => true, 
                'message' => 'Đơn hàng đã được tạo thành công',
                'order_id' => $result['order_id']
            ]);
        } else {
            http_response_code(400);
            echo json_encode(['error' => $result['message']]);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

// =============================================
// admin.php - Simple Admin Panel
// =============================================
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SpicyIP Admin Panel</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: Arial, sans-serif; 
            background: #f5f5f5; 
            padding: 20px;
        }
        .container { 
            max-width: 1200px; 
            margin: 0 auto; 
            background: white; 
            padding: 20px; 
            border-radius: 10px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 { 
            color: #333; 
            margin-bottom: 30px; 
            text-align: center;
            background: linear-gradient(45deg, #ff6b6b 0%, #ee5a24 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .tabs { 
            display: flex; 
            margin-bottom: 20px; 
            border-bottom: 1px solid #ddd;
        }
        .tab { 
            padding: 15px 30px; 
            cursor: pointer; 
            border: none; 
            background: none; 
            font-size: 16px;
            color: #666;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }
        .tab.active { 
            color: #ff6b6b; 
            border-bottom-color: #ff6b6b;
        }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 20px;
        }
        th, td { 
            padding: 12px; 
            text-align: left; 
            border-bottom: 1px solid #ddd;
        }
        th { 
            background: #f8f9fa; 
            font-weight: bold;
            color: #333;
        }
        tr:hover { background: #f8f9fa; }
        .status { 
            padding: 5px 10px; 
            border-radius: 15px; 
            color: white; 
            font-size: 12px;
        }
        .status.available { background: #28a745; }
        .status.sold { background: #dc3545; }
        .status.reserved { background: #ffc107; color: #000; }
        .btn { 
            padding: 8px 16px; 
            border: none; 
            border-radius: 5px; 
            cursor: pointer; 
            margin: 2px;
            transition: all 0.3s;
        }
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn:hover { opacity: 0.8; transform: translateY(-2px); }
    </style>
</head>
<body>
    <div class="container">
        <h1>🌶️ SpicyIP Admin Panel</h1>
        
        <div class="tabs">
            <button class="tab active" onclick="showTab('artworks')">Tác phẩm</button>
            <button class="tab" onclick="showTab('orders')">Đơn hàng</button>
            <button class="tab" onclick="showTab('artists')">Họa sĩ</button>
            <button class="tab" onclick="showTab('analytics')">Thống kê</button>
        </div>

        <div id="artworks" class="tab-content active">
            <h2>Quản lý Tác phẩm</h2>
            <button class="btn btn-primary" onclick="addArtwork()">Thêm tác phẩm</button>
            
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tên tác phẩm</th>
                        <th>Họa sĩ</th>
                        <th>Giá</th>
                        <th>Trạng thái</th>
                        <th>Hành động</th>
                    </tr>
                </thead>
                <tbody id="artworks-table">
                    <!-- Data will be loaded here -->
                </tbody>
            </table>
        </div>

        <div id="orders" class="tab-content">
            <h2>Quản lý Đơn hàng</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Khách hàng</th>
                        <th>Email</th>
                        <th>Tác phẩm</th>
                        <th>Trạng thái</th>
                        <th>Ngày đặt</th>
                        <th>Hành động</th>
                    </tr>
                </thead>
                <tbody id="orders-table">
                    <!-- Data will be loaded here -->
                </tbody>
            </table>
        </div>

        <div id="artists" class="tab-content">
            <h2>Quản lý Họa sĩ</h2>
            <button class="btn btn-primary" onclick="addArtist()">Thêm họa sĩ</button>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Họ tên</th>
                        <th>Năm sinh</th>
                        <th>Quê quán</th>
                        <th>Chuyên môn</th>
                        <th>Hành động</th>
                    </tr>
                </thead>
                <tbody id="artists-table">
                    <!-- Data will be loaded here -->
                </tbody>
            </table>
        </div>

        <div id="analytics" class="tab-content">
            <h2>Thống kê</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">
                <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; text-align: center;">
                    <h3 style="color: #28a745;">Tổng tác phẩm</h3>
                    <div style="font-size: 2rem; font-weight: bold; color: #333;" id="total-artworks">0</div>
                </div>
                <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; text-align: center;">
                    <h3 style="color: #007bff;">Đơn hàng</h3>
                    <div style="font-size: 2rem; font-weight: bold; color: #333;" id="total-orders">0</div>
                </div>
                <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; text-align: center;">
                    <h3 style="color: #ffc107;">Doanh thu</h3>
                    <div style="font-size: 2rem; font-weight: bold; color: #333;" id="total-revenue">0đ</div>
                </div>
                <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; text-align: center;">
                    <h3 style="color: #dc3545;">Họa sĩ</h3>
                    <div style="font-size: 2rem; font-weight: bold; color: #333;" id="total-artists">0</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });

            // Show selected tab
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');

            // Load data for the tab
            switch(tabName) {
                case 'artworks':
                    loadArtworks();
                    break;
                case 'orders':
                    loadOrders();
                    break;
                case 'artists':
                    loadArtists();
                    break;
                case 'analytics':
                    loadAnalytics();
                    break;
            }
        }

        function loadArtworks() {
            // Simulate loading data
            const tableBody = document.getElementById('artworks-table');
            tableBody.innerHTML = `
                <tr>
                    <td>1</td>
                    <td>Mặt trời rực rỡ</td>
                    <td>Nguyễn Văn A</td>
                    <td>2.500.000đ</td>
                    <td><span class="status available">Có sẵn</span></td>
                    <td>
                        <button class="btn btn-primary">Sửa</button>
                        <button class="btn btn-danger">Xóa</button>
                    </td>
                </tr>
                <tr>
                    <td>2</td>
                    <td>Đêm thanh bình</td>
                    <td>Trần Thị B</td>
                    <td>3.200.000đ</td>
                    <td><span class="status reserved">Đã đặt</span></td>
                    <td>
                        <button class="btn btn-primary">Sửa</button>
                        <button class="btn btn-danger">Xóa</button>
                    </td>
                </tr>
            `;
        }

        function loadOrders() {
            const tableBody = document.getElementById('orders-table');
            tableBody.innerHTML = `
                <tr>
                    <td>1</td>
                    <td>Nguyễn Văn X</td>
                    <td>customer@example.com</td>
                    <td>Mặt trời rực rỡ</td>
                    <td><span class="status reserved">Đang xử lý</span></td>
                    <td>2024-08-30</td>
                    <td>
                        <button class="btn btn-success">Xác nhận</button>
                        <button class="btn btn-danger">Hủy</button>
                    </td>
                </tr>
            `;
        }

        function loadArtists() {
            const tableBody = document.getElementById('artists-table');
            tableBody.innerHTML = `
                <tr>
                    <td>1</td>
                    <td>Nguyễn Văn A</td>
                    <td>1985</td>
                    <td>Hà Nội</td>
                    <td>Hội họa đương đại</td>
                    <td>
                        <button class="btn btn-primary">Sửa</button>
                        <button class="btn btn-danger">Xóa</button>
                    </td>
                </tr>
                <tr>
                    <td>2</td>
                    <td>Trần Thị B</td>
                    <td>1978</td>
                    <td>TP.HCM</td>
                    <td>Tranh tĩnh vật</td>
                    <td>
                        <button class="btn btn-primary">Sửa</button>
                        <button class="btn btn-danger">Xóa</button>
                    </td>
                </tr>
            `;
        }

        function loadAnalytics() {
            document.getElementById('total-artworks').textContent = '6';
            document.getElementById('total-orders').textContent = '12';
            document.getElementById('total-revenue').textContent = '15.200.000';
            document.getElementById('total-artists').textContent = '6';
        }

        function addArtwork() {
            alert('Chức năng thêm tác phẩm sẽ được phát triển');
        }

        function addArtist() {
            alert('Chức năng thêm họa sĩ sẽ được phát triển');
        }

        // Load initial data
        loadArtworks();
    </script>
</body>
</html>

<?php
// =============================================
// .htaccess file content
// =============================================
/*
RewriteEngine On

# API Routes
RewriteRule ^api/(.*)$ api.php [QSA,L]

# Frontend Routes - serve index.html for all non-API routes
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_URI} !^/api/
RewriteRule ^(.*)$ index.html [QSA,L]

# Security Headers
Header always set X-Frame-Options DENY
Header always set X-Content-Type-Options nosniff
Header always set X-XSS-Protection "1; mode=block"
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"

# Compression
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/plain
    AddOutputFilterByType DEFLATE text/html
    AddOutputFilterByType DEFLATE text/xml
    AddOutputFilterByType DEFLATE text/css
    AddOutputFilterByType DEFLATE application/xml
    AddOutputFilterByType DEFLATE application/xhtml+xml
    AddOutputFilterByType DEFLATE application/rss+xml
    AddOutputFilterByType DEFLATE application/javascript
    AddOutputFilterByType DEFLATE application/x-javascript
</IfModule>

# Browser Caching
<IfModule mod_expires.c>
    ExpiresActive on
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
    ExpiresByType image/png "access plus 1 month"
    ExpiresByType image/jpg "access plus 1 month"
    ExpiresByType image/jpeg "access plus 1 month"
    ExpiresByType image/gif "access plus 1 month"
    ExpiresByType image/svg+xml "access plus 1 month"
</IfModule>
*/
?>
