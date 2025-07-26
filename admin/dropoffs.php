<?php
require_once '../config.php';
require_once '../functions.php';

if (!is_logged_in() || !has_role('admin')) {
    redirect('../login.php');
}

$conn = db_connect();

$action = $_GET['action'] ?? 'list';
$location_id = $_GET['id'] ?? 0;
$name = $address = $latitude = $longitude = "";
$errors = [];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize_input($_POST['name']);
    $address = sanitize_input($_POST['address']);
    $latitude = sanitize_input($_POST['latitude']);
    $longitude = sanitize_input($_POST['longitude']);
    $location_id = (int)($_POST['location_id'] ?? 0);

    if (empty($name) || empty($address)) {
        $errors[] = "Name and address are required.";
    }

    if (empty($errors)) {
        if ($location_id > 0) { // Update
            $sql = "UPDATE drop_off_locations SET name=?, address=?, latitude=?, longitude=? WHERE location_id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssdi", $name, $address, $latitude, $longitude, $location_id);
        } else { // Create
            $sql = "INSERT INTO drop_off_locations (name, address, latitude, longitude) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssdd", $name, $address, $latitude, $longitude);
        }
        if ($stmt->execute()) {
            redirect('dropoffs.php');
        } else {
            $errors[] = "Database error. Please try again.";
        }
        mysqli_stmt_close($stmt);
    }
}

// Handle delete
if ($action === 'delete' && $location_id > 0) {
    $sql = "DELETE FROM drop_off_locations WHERE location_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $location_id);
    $stmt->execute();
    $stmt->close();
    redirect('dropoffs.php');
}

// Fetch data for editing
if ($action === 'edit' && $location_id > 0) {
    $sql = "SELECT * FROM drop_off_locations WHERE location_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $location_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $location = $result->fetch_assoc();
    if ($location) {
        extract($location);
    }
}

// Fetch all locations for listing
$locations = [];
$sql = "SELECT * FROM drop_off_locations ORDER BY name";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $locations[] = $row;
    }
    $result->free();
}
$page_title = 'Manage Drop-off Locations';
$is_admin_page = true;
require_once '../templates/header.php';
?>

    <div class="container" id="manage-dropoffs-container">
        <div class="form-container" id="dropoff-form-section">
            <h2><?php echo ($action === 'edit') ? 'Edit' : 'Add'; ?> Drop-off Location</h2>
            <?php if (!empty($errors)): ?><div class="alert alert-danger"><?php echo implode('<br>', $errors); ?></div><?php endif; ?>
            <form action="dropoffs.php" method="post">
                <input type="hidden" name="location_id" value="<?php echo $location_id; ?>">
                <div class="form-group"><label>Name</label><input type="text" name="name" value="<?php echo htmlspecialchars($name); ?>" required></div>
                <div class="form-group"><label>Address</label><input type="text" name="address" value="<?php echo htmlspecialchars($address); ?>" required></div>
                <div class="form-group"><label>Latitude</label><input type="text" name="latitude" value="<?php echo htmlspecialchars($latitude); ?>"></div>
                <div class="form-group"><label>Longitude</label><input type="text" name="longitude" value="<?php echo htmlspecialchars($longitude); ?>"></div>
                <button type="submit" class="btn"><?php echo ($action === 'edit') ? 'Update' : 'Add'; ?> Location</button>
            </form>
        </div>

        <div id="dropoff-list-section">
            <h2>Existing Locations</h2>
            <table class="data-table">
                <thead><tr><th>Name</th><th>Address</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($locations as $loc): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($loc['name']); ?></td>
                        <td><?php echo htmlspecialchars($loc['address']); ?></td>
                        <td>
                            <a href="?action=edit&id=<?php echo $loc['location_id']; ?>" class="btn">Edit</a>
                            <a href="?action=delete&id=<?php echo $loc['location_id']; ?>" class="btn btn-delete" onclick="return confirm('Are you sure?');">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php 
$conn->close();
require_once '../templates/footer.php'; 
?>
