<?php
require_once 'config.php';
checkLogin();

// Only admin and super_admin can add staff
if (!hasPermission('admin')) {
    header("Location: dashboard.php");
    exit();
}

$message = '';
$message_type = '';

// Generate Staff ID
function generateStaffId()
{
    global $conn;

    do {
        $staff_id = 'STF-' . date('Y') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
        $check = $conn->query("SELECT id FROM staff WHERE staff_id = '$staff_id'");
    } while ($check->num_rows > 0);

    return $staff_id;
}

// Handle Add Staff Member
if (isset($_POST['add_staff'])) {
    $staff_id = generateStaffId();
    $full_name = sanitize($_POST['full_name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $employee_id = sanitize($_POST['employee_id']);
    $position = sanitize($_POST['position']);
    $department = sanitize($_POST['department']);
    $section = sanitize($_POST['section']);
    $floor = sanitize($_POST['floor']);
    $date_joined = !empty($_POST['date_joined']) ? sanitize($_POST['date_joined']) : date('Y-m-d');
    $notes = sanitize($_POST['notes']);

    // Check if email already exists
    $check_email = $conn->query("SELECT id FROM staff WHERE email = '$email'");
    if ($check_email->num_rows > 0) {
        $message = "Email already exists! Please use a different email.";
        $message_type = "danger";
    } else {
        $stmt = $conn->prepare("INSERT INTO staff (staff_id, full_name, email, phone, employee_id, position, department, section, floor, date_joined, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssssssssi", $staff_id, $full_name, $email, $phone, $employee_id, $position, $department, $section, $floor, $date_joined, $notes, $_SESSION['user_id']);

        if ($stmt->execute()) {
            $new_staff_id = $conn->insert_id;
            logActivity($_SESSION['user_id'], 'CREATE', 'staff', $new_staff_id, "Added staff member: $full_name");

            // Redirect with success
            header("Location: add_staff.php?success=1&staff_id=$staff_id&name=" . urlencode($full_name));
            exit();
        } else {
            $message = "Error adding staff member: " . $conn->error;
            $message_type = "danger";
        }
        $stmt->close();
    }
}

// Handle success redirect
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $staff_id = isset($_GET['staff_id']) ? htmlspecialchars($_GET['staff_id']) : '';
    $name = isset($_GET['name']) ? htmlspecialchars($_GET['name']) : '';
    $message = "Staff member <strong>$name</strong> added successfully! Staff ID: <strong>$staff_id</strong>";
    $message_type = "success";
}

// Get existing departments for suggestions
$departments = $conn->query("SELECT DISTINCT department FROM staff WHERE department IS NOT NULL AND department != '' ORDER BY department");

// Get existing floors for suggestions
$floors = $conn->query("SELECT DISTINCT floor FROM staff WHERE floor IS NOT NULL AND floor != '' ORDER BY floor");


// Get all departments from department_sections table
$department_options = [];
$departments_query = $conn->query("SELECT DISTINCT department_name FROM department_sections ORDER BY department_name");
if ($departments_query) {
    while ($opt = $departments_query->fetch_assoc()) {
        $department_options[] = $opt['department_name'];
    }
}

// Define floors (standard office floors)
$floor_options = ['Ground Floor', '1st Floor', '2nd Floor', '3rd Floor', '4th Floor', '5th Floor'];

// Get departments list for dropdowns
$departments_list = $department_options;

// Build department-sections mapping from database
$department_sections = [];
$sections_query = $conn->query("SELECT department_name, section_name FROM department_sections ORDER BY department_name, section_name");
if ($sections_query) {
    while ($row = $sections_query->fetch_assoc()) {
        $dept = $row['department_name'];
        $section = $row['section_name'];

        if (!isset($department_sections[$dept])) {
            $department_sections[$dept] = [];
        }
        $department_sections[$dept][] = $section;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Staff Member - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --sidebar-width: 260px;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            overflow-y: auto;
            z-index: 1000;
        }

        .sidebar-header {
            padding: 25px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 12px 25px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border-left: 4px solid white;
        }

        .sidebar-menu i {
            width: 25px;
            margin-right: 12px;
        }

        .main-content {
            margin-left: var(--sidebar-width);
        }

        .top-navbar {
            background: white;
            padding: 15px 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .content-area {
            padding: 30px;
        }

        .card-custom {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            background: white;
        }

        .form-label {
            font-weight: 600;
            color: #555;
            margin-bottom: 8px;
        }

        .form-control,
        .form-select {
            border-radius: 8px;
            border: 1px solid #ddd;
            padding: 10px 15px;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .section-title {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e0e0e0;
        }

        .info-box {
            background: #f8f9fa;
            border-left: 4px solid var(--primary-color);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .password-strength {
            height: 5px;
            border-radius: 3px;
            margin-top: 5px;
            transition: all 0.3s;
        }

        .datalist-option {
            padding: 8px;
            cursor: pointer;
        }

        .datalist-option:hover {
            background: #f0f0f0;
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h4 class="mb-0"><i class="fas fa-laptop me-2"></i><?php echo APP_NAME; ?></h4>
        </div>
        <div class="sidebar-menu">
            <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <a href="assets.php"><i class="fas fa-box"></i> Assets</a>
            <a href="assignments.php"><i class="fas fa-exchange-alt"></i> Assignments</a>
            <a href="staff.php" class="active"><i class="fas fa-users"></i> Staff Directory</a>
            <a href="categories.php"><i class="fas fa-tags"></i> Categories</a>
            <a href="suppliers.php"><i class="fas fa-truck"></i> Suppliers</a>
            <a href="users.php"><i class="fas fa-user-cog"></i> User Management</a>
            <a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
            <a href="import.php"><i class="fas fa-file-import"></i> Import Assets</a>
            <a href="inspections.php"><i class="fas fa-clipboard-check"></i> Inspections</a>
            <hr style="border-color: rgba(255,255,255,0.1);">
            <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-navbar">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0">Add New Staff Member</h5>
                    <small class="text-muted">Add staff members who can be assigned company assets</small>
                </div>
                <a href="staff.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Staff Directory
                </a>
            </div>
        </div>

        <div class="content-area">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    <?php if ($message_type === 'success'): ?>
                        <div class="mt-3">
                            <a href="add_staff.php" class="btn btn-sm btn-success me-2">
                                <i class="fas fa-plus me-1"></i>Add Another Staff Member
                            </a>
                            <a href="staff.php" class="btn btn-sm btn-primary">
                                <i class="fas fa-users me-1"></i>View Staff Directory
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="row g-4">
                    <div class="col-lg-8">
                        <!-- Account Information -->
                        <div class="card card-custom mb-4">
                            <div class="card-header bg-white py-3">
                                <h6 class="mb-0 section-title"><i class="fas fa-info-circle me-2"></i>Staff Information</h6>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info small mb-3">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>Staff ID will be auto-generated:</strong> Format: STF-YYYY-###
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                        <input type="text" name="full_name" class="form-control" placeholder="e.g., John Doe" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Email <span class="text-danger">*</span></label>
                                        <input type="email" name="email" class="form-control" placeholder="e.g., john.doe@company.com" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Phone Number</label>
                                        <input type="text" name="phone" class="form-control" placeholder="e.g., +268 7612 3456">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Date Joined</label>
                                        <input type="date" name="date_joined" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Employment Details -->
                        <div class="card card-custom mb-4">
                            <div class="card-header bg-white py-3">
                                <h6 class="mb-0 section-title"><i class="fas fa-id-card me-2"></i>Employment Details</h6>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Employee ID</label>
                                        <input type="text" name="employee_id" class="form-control" placeholder="e.g., EMP-2024-001">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Position/Title</label>
                                        <input type="text" name="position" class="form-control" placeholder="e.g., IT Officer">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Department & Location -->
                        <div class="card card-custom">
                            <div class="card-header bg-white py-3">
                                <h6 class="mb-0 section-title"><i class="fas fa-building me-2"></i>Department & Location</h6>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Department <span class="text-danger">*</span></label>
                                        <select name="department" id="departmentSelect" class="form-select" required>
                                            <option value="">Select Department</option>
                                            <?php foreach ($department_options as $dept): ?>
                                                <option value="<?php echo htmlspecialchars($dept); ?>">
                                                    <?php echo htmlspecialchars($dept); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="text-muted">Select from predefined departments</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Section <span class="text-danger">*</span></label>
                                    <select name="section" id="sectionSelect" class="form-select" required>
                                        <option value="">Select Section</option>
                                    </select>
                                    <small class="text-muted">Section will populate based on department</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Floor <span class="text-danger">*</span></label>
                                    <select name="floor" class="form-select" required>
                                        <option value="">Select Floor</option>
                                        <option value="Ground Floor">Ground Floor</option>
                                        <option value="1st Floor">1st Floor</option>
                                        <option value="2nd Floor">2nd Floor</option>
                                        <option value="3rd Floor">3rd Floor</option>
                                        <option value="4th Floor">4th Floor</option>
                                        <option value="5th Floor">5th Floor</option>
                                        <?php while ($floor = $floors->fetch_assoc()): ?>
                                            <?php
                                            $floor_name = $floor['floor'];
                                            // Only show if not in predefined list
                                            if (!in_array($floor_name, $floor_options)):
                                            ?>
                                                <option value="<?php echo htmlspecialchars($floor_name); ?>">
                                                    <?php echo htmlspecialchars($floor_name); ?>
                                                </option>
                                            <?php endif; ?>
                                        <?php endwhile; ?>
                                    </select>
                                    <small class="text-muted">Select staff member's floor location</small>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Additional Notes</label>
                                    <textarea name="notes" class="form-control" rows="3" placeholder="Any additional information about this staff member..."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Sidebar -->
                <div class="col-lg-4">
                    <div class="card card-custom sticky-top" style="top: 20px;">
                        <div class="card-header bg-white py-3">
                            <h6 class="mb-0"><i class="fas fa-save me-2"></i>Save Staff Member</h6>
                        </div>
                        <div class="card-body">
                            <div class="info-box mb-3">
                                <h6><i class="fas fa-info-circle me-2"></i>Important</h6>
                                <ul class="small mb-0 ps-3">
                                    <li>All fields marked with <span class="text-danger">*</span> are required</li>
                                    <li>Staff ID will be auto-generated (STF-YYYY-###)</li>
                                    <li>Email must be unique</li>
                                    <li>Staff members are stored separately from system users</li>
                                    <li>Staff can be assigned assets after creation</li>
                                </ul>
                            </div>

                            <button type="submit" name="add_staff" class="btn btn-primary w-100 mb-2">
                                <i class="fas fa-user-plus me-2"></i>Add Staff Member
                            </button>
                            <a href="staff.php" class="btn btn-outline-secondary w-100">
                                <i class="fas fa-times me-2"></i>Cancel
                            </a>

                            <hr class="my-3">

                            <div class="small text-muted">
                                <p class="mb-2"><i class="fas fa-user me-1"></i> <strong>Adding as:</strong><br><?php echo htmlspecialchars($_SESSION['full_name']); ?></p>
                                <p class="mb-0"><i class="fas fa-calendar me-1"></i> <strong>Date:</strong><br><?php echo date('d M Y, h:i A'); ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Role Descriptions -->
                    <div class="card card-custom mt-3">
                        <div class="card-header bg-white py-3">
                            <h6 class="mb-0"><i class="fas fa-users me-2"></i>Staff vs Users</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <span class="badge bg-primary mb-2">Staff Members</span>
                                <p class="small mb-0">Company employees who receive asset assignments. No system login access.</p>
                            </div>
                            <div>
                                <span class="badge bg-secondary mb-2">System Users</span>
                                <p class="small mb-0">Admin/Staff with system login credentials for managing inventory. See User Management page.</p>
                            </div>
                        </div>
                    </div>
                </div>
        </div>
        </form>
    </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Department to Sections mapping
        const departmentSections = <?php echo json_encode($department_sections); ?>;

        const departmentSelect = document.getElementById('departmentSelect');
        const sectionSelect = document.getElementById('sectionSelect');

        departmentSelect.addEventListener('change', function() {
            const selectedDept = this.value;

            // Clear section select
            sectionSelect.innerHTML = '<option value="">Select Section</option>';

            if (selectedDept && departmentSections[selectedDept]) {
                // Populate sections
                departmentSections[selectedDept].forEach(section => {
                    const option = document.createElement('option');
                    option.value = section;
                    option.textContent = section;
                    sectionSelect.appendChild(option);
                });

                // Enable section select
                sectionSelect.disabled = false;
                sectionSelect.classList.remove('section-disabled');
            } else {
                // Disable section select
                sectionSelect.disabled = true;
                sectionSelect.classList.add('section-disabled');
            }
        });
    </script>

</body>

</html>