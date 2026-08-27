<?php

function log_activity($action, $module, $description = '', $data_before = null, $data_after = null) {
    global $pdo;
    
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    try {
        $user_id = $_SESSION['user_id'];
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        $data_before_json = $data_before ? json_encode($data_before, JSON_UNESCAPED_UNICODE) : null;
        $data_after_json = $data_after ? json_encode($data_after, JSON_UNESCAPED_UNICODE) : null;
        
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs 
            (user_id, action, module, description, data_before, data_after, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([
            $user_id,
            $action,
            $module,
            $description,
            $data_before_json,
            $data_after_json,
            $ip_address,
            $user_agent
        ]);
    } catch (PDOException $e) {
        error_log("Activity log error: " . $e->getMessage());
        return false;
    }
}

function get_activity_logs($filters = []) {
    global $pdo;
    
    $where = [];
    $params = [];
    
    if (!empty($filters['user_id'])) {
        $where[] = "al.user_id = ?";
        $params[] = $filters['user_id'];
    }
    
    if (!empty($filters['action'])) {
        $where[] = "al.action = ?";
        $params[] = $filters['action'];
    }
    
    if (!empty($filters['module'])) {
        $where[] = "al.module = ?";
        $params[] = $filters['module'];
    }
    
    if (!empty($filters['date_from'])) {
        $where[] = "DATE(al.created_at) >= ?";
        $params[] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $where[] = "DATE(al.created_at) <= ?";
        $params[] = $filters['date_to'];
    }
    
    if (!empty($filters['search'])) {
        $where[] = "(al.description LIKE ? OR u.nama LIKE ?)";
        $search = '%' . $filters['search'] . '%';
        $params[] = $search;
        $params[] = $search;
    }
    
    $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $limit = $filters['limit'] ?? 50;
    $offset = $filters['offset'] ?? 0;
    
    $sql = "
        SELECT 
            al.*,
            u.nama as user_nama,
            u.nip as user_nip,
            r.nama as user_role
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.id
        LEFT JOIN m_roles r ON u.role_id = r.id
        $where_clause
        ORDER BY al.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function count_activity_logs($filters = []) {
    global $pdo;
    
    $where = [];
    $params = [];
    
    if (!empty($filters['user_id'])) {
        $where[] = "al.user_id = ?";
        $params[] = $filters['user_id'];
    }
    
    if (!empty($filters['action'])) {
        $where[] = "al.action = ?";
        $params[] = $filters['action'];
    }
    
    if (!empty($filters['module'])) {
        $where[] = "al.module = ?";
        $params[] = $filters['module'];
    }
    
    if (!empty($filters['date_from'])) {
        $where[] = "DATE(al.created_at) >= ?";
        $params[] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $where[] = "DATE(al.created_at) <= ?";
        $params[] = $filters['date_to'];
    }
    
    if (!empty($filters['search'])) {
        $where[] = "(al.description LIKE ? OR u.nama LIKE ?)";
        $search = '%' . $filters['search'] . '%';
        $params[] = $search;
        $params[] = $search;
    }
    
    $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $sql = "
        SELECT COUNT(*) as total
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.id
        $where_clause
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
}
