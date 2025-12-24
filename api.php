<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Content-Type: application/json; charset=UTF-8");

// Turn off default error reporting to keep JSON clean
error_reporting(0); 

include 'db.php';

// --- ENCRYPTION LOGIC ---
$key = "ayush"; // Secret Key

function encrypt($text, $key) {
    return base64_encode(openssl_encrypt($text, "AES-128-ECB", $key));
}

function decrypt($text, $key) {
    return openssl_decrypt(base64_decode($text), "AES-128-ECB", $key);
}

$action = isset($_POST['action']) ? $_POST['action'] : '';
$response = array('success' => false);

try {
    // --- AUTHENTICATION ---
    if ($action == 'signup') {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
        
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
             echo json_encode(['success' => false, 'message' => 'Email already registered']);
             exit;
        }

        $stmt = $conn->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $name, $email, $pass);
        if ($stmt->execute()) {
            $response['success'] = true;
            $response['name'] = $name;
        } else {
            $response['message'] = "Database error during signup";
        }
    }

    elseif ($action == 'login') {
        $email = trim($_POST['email']);
        $pass = $_POST['password'];
        $stmt = $conn->prepare("SELECT name, password FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            if (password_verify($pass, $row['password'])) {
                $response['success'] = true;
                $response['name'] = $row['name'];
            } else $response['message'] = "Invalid password";
        } else $response['message'] = "User not found";
    }

    // --- GROUPS ---
    elseif ($action == 'create_group') {
        $room = trim($_POST['room_name']);
        $pass = $_POST['password'];
        $email = $_POST['email'];
        
        $stmt = $conn->prepare("INSERT INTO rooms (name, password, type) VALUES (?, ?, 'group')");
        $stmt->bind_param("ss", $room, $pass);
        
        if ($stmt->execute()) {
            $stmt2 = $conn->prepare("INSERT INTO room_members (user_email, room_name, last_read) VALUES (?, ?, NOW())");
            $stmt2->bind_param("ss", $email, $room);
            $stmt2->execute();
            $response['success'] = true;
        } else $response['message'] = "Group name taken";
    }

    elseif ($action == 'join_group') {
        $room = trim($_POST['room_name']);
        $pass = $_POST['password'];
        $email = $_POST['email'];

        $stmt = $conn->prepare("SELECT password FROM rooms WHERE name = ? AND type = 'group'");
        $stmt->bind_param("s", $room);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            if ($row['password'] == $pass || $row['password'] == "") {
                $stmt2 = $conn->prepare("INSERT IGNORE INTO room_members (user_email, room_name, last_read) VALUES (?, ?, NOW())");
                $stmt2->bind_param("ss", $email, $room);
                if ($stmt2->execute()) {
                    $response['success'] = true;
                } else $response['message'] = "Could not join room";
            } else $response['message'] = "Incorrect password";
        } else $response['message'] = "Group not found";
    }

    // --- DIRECT MESSAGES ---
    elseif ($action == 'create_dm') {
        $my_email = trim($_POST['email']);
        $target_email = trim($_POST['target_email']);

        if ($my_email === $target_email) {
            echo json_encode(['success' => false, 'message' => 'You cannot DM yourself']);
            exit;
        }

        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $target_email);
        $check->execute();
        if ($check->get_result()->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'User not found. Check the email address.']);
            exit;
        }

        $emails = array($my_email, $target_email);
        sort($emails); 
        $room_id = "dm_" . md5($emails[0] . $emails[1]); 

        $stmt = $conn->prepare("INSERT IGNORE INTO rooms (name, type) VALUES (?, 'dm')");
        $stmt->bind_param("s", $room_id);
        $stmt->execute();

        $stmt2 = $conn->prepare("INSERT IGNORE INTO room_members (user_email, room_name, last_read) VALUES (?, ?, NOW()), (?, ?, NOW())");
        $stmt2->bind_param("ssss", $my_email, $room_id, $target_email, $room_id);
        $stmt2->execute();

        $response['success'] = true;
    }

    // --- MARK READ ---
    elseif ($action == 'mark_read') {
        $room = $_POST['room_name'];
        $email = $_POST['email'];
        $stmt = $conn->prepare("UPDATE room_members SET last_read = NOW() WHERE room_name = ? AND user_email = ?");
        $stmt->bind_param("ss", $room, $email);
        $stmt->execute();
        $response['success'] = true;
    }

    // --- GET ROOMS WITH UNREAD COUNTS ---
    elseif ($action == 'get_rooms') {
        $email = $_POST['email'];
        
        $sql = "
            SELECT 
                r.name as id, 
                r.type, 
                r.name as display_name,
                (SELECT COUNT(*) 
                 FROM messages m 
                 WHERE m.room_name = r.name 
                 AND m.created_at > IFNULL(rm.last_read, '1000-01-01')
                ) as unread
            FROM room_members rm 
            JOIN rooms r ON rm.room_name = r.name 
            WHERE rm.user_email = ? 
            ORDER BY rm.joined_at DESC
        ";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $rooms = array();
        while ($row = $result->fetch_assoc()) {
            if ($row['type'] == 'dm') {
                $sub = $conn->prepare("
                    SELECT u.name 
                    FROM room_members rm 
                    JOIN users u ON rm.user_email = u.email 
                    WHERE rm.room_name = ? AND rm.user_email != ? 
                    LIMIT 1
                ");
                $sub->bind_param("ss", $row['id'], $email);
                $sub->execute();
                $sub_res = $sub->get_result();
                if ($r = $sub_res->fetch_assoc()) {
                    $row['display_name'] = $r['name'];
                } else {
                    $row['display_name'] = "Unknown User";
                }
            }
            $rooms[] = $row;
        }
        $response['success'] = true;
        $response['rooms'] = $rooms;
    }

    // --- MESSAGING ---
    elseif ($action == 'send_message') {
        $user = $_POST['username'];
        $email = $_POST['email'];
        $room = $_POST['room_name'];
        $msg_raw = $_POST['message'];
        
        $msg_enc = encrypt($msg_raw, $key);

        $type = 'text';
        $file_path = null;

        if (isset($_FILES['file']) && $_FILES['file']['error'] == 0) {
            $target_dir = "uploads/";
            if (!file_exists($target_dir)) mkdir($target_dir, 0755, true);
            
            $ext = strtolower(pathinfo($_FILES["file"]["name"], PATHINFO_EXTENSION));
            $file_name = time() . "_" . rand(1000,9999) . "." . $ext;
            $target_file = $target_dir . $file_name;
            
            if (move_uploaded_file($_FILES["file"]["tmp_name"], $target_file)) {
                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                $base_url = $protocol . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/";
                $file_path = $base_url . $target_file;
                
                // --- ROBUST FILE TYPE DETECTION ---
                $image_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
                $video_exts = ['mp4', 'webm', 'ogg', 'mov', 'avi', 'mkv', '3gp', 'm4v'];

                if (in_array($ext, $image_exts)) {
                    $type = 'image';
                } elseif (in_array($ext, $video_exts)) {
                    $type = 'video'; // <--- THIS WAS MISSING/DEFAULTING TO FILE
                } else {
                    $type = 'file';
                }
            }
        }

        $stmt = $conn->prepare("INSERT INTO messages (room_name, username, user_email, message, type, file_data) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $room, $user, $email, $msg_enc, $type, $file_path);
        
        if ($stmt->execute()) {
            $stmt2 = $conn->prepare("UPDATE room_members SET last_read = NOW() WHERE room_name = ? AND user_email = ?");
            $stmt2->bind_param("ss", $room, $email);
            $stmt2->execute();
            $response['success'] = true;
        }
    }

    // --- GET MESSAGES WITH READ STATUS ---
    elseif ($action == 'get_messages') {
        $room = $_POST['room_name'];
        $my_email = isset($_POST['email']) ? $_POST['email'] : '';

        // 1. Get messages
        $stmt = $conn->prepare("SELECT * FROM messages WHERE room_name = ? ORDER BY created_at ASC LIMIT 100");
        $stmt->bind_param("s", $room);
        $stmt->execute();
        $result = $stmt->get_result();
        
        // 2. Get the *other* person's last_read time (for DMs)
        $other_read_time = null;
        if ($my_email) {
            $stmt2 = $conn->prepare("SELECT MAX(last_read) as lr FROM room_members WHERE room_name = ? AND user_email != ?");
            $stmt2->bind_param("ss", $room, $my_email);
            $stmt2->execute();
            $res2 = $stmt2->get_result();
            if ($row2 = $res2->fetch_assoc()) {
                $other_read_time = $row2['lr'];
            }
        }

        $messages = array();
        while ($row = $result->fetch_assoc()) {
            if ($row['message']) {
                $row['message'] = decrypt($row['message'], $key);
            }
            
            // Determine if seen
            $row['is_seen'] = false;
            if ($other_read_time && $row['created_at'] <= $other_read_time) {
                $row['is_seen'] = true;
            }

            $messages[] = $row;
        }
        $response['success'] = true;
        $response['messages'] = $messages;
    }

} catch (Exception $e) {
    $response['message'] = "Server Error: " . $e->getMessage();
}

echo json_encode($response);
$conn->close();
?>