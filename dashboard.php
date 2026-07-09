<?php include_once("inc/header.php"); ?>
<?php $class = str_replace(".php","",basename(__FILE__)); ?>

<?php

if (!isset($_SESSION["username"])) {

header("Location: index.php");
exit();
}

require __DIR__ . "/db.php";
?>
<div class="<?php echo $class; ?>">
    <div class="navbar">

        <h2>User Management System</h2>

        <div class="logout-area">


            Welcome,
            <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>

            <div class="logout-card">
                <button type="button" class="logout-btn" id="logoutBtn">Logout</button>

                <!-- Modal -->
                <div class="logout-modal" id="logoutModal" aria-hidden="true">
                    <div class="logout-modal-content" role="dialog" aria-modal="true" aria-labelledby="logoutModalTitle">
                        <h3 id="logoutModalTitle">Confirm Logout</h3>
                        <p>Are you sure you want to logout?</p>

                        <div class="logout-modal-actions">
                            <button type="button" class="logout-cancel" id="logoutCancel">Cancel</button>

                            <form action="logout.php" method="GET" id="logoutForm" style="margin:0;">
                                <button type="submit" class="logout-confirm-btn" id="logoutConfirmBtn">Logout</button>
                            </form>
                        </div>
                    </div>
                </div>

                <script>
                    (function () {
                        const logoutBtn = document.getElementById('logoutBtn');
                        const logoutModal = document.getElementById('logoutModal');
                        const logoutCancel = document.getElementById('logoutCancel');

                        if (!logoutBtn || !logoutModal || !logoutCancel) return;

                        const show = () => logoutModal.classList.add('show');
                        const hide = () => logoutModal.classList.remove('show');

                        logoutBtn.addEventListener('click', show);
                        logoutCancel.addEventListener('click', hide);

                        logoutModal.addEventListener('click', function (e) {
                            if (e.target === logoutModal) hide();
                        });

                        document.addEventListener('keydown', function (e) {
                            if (e.key === 'Escape') hide();
                        });
                    })();
                </script>
            </div>

        </div>

    </div>

    <hr>
    <div class="card">
        <h2>Add User</h2>
    <form id="userForm" action="insert.php" method="POST">

        <label>Name:</label><br>
        <input type="text" id="name" name="name">
        <span id="nameError" class="error-text"></span><br><br>

        <label>Age:</label><br>
        <input type="number" id="age" name="age">
        <span id="ageError" style="color:red;"></span><br><br>

        <label>Gender:</label><br>
        <select id="gender" name="gender">
        <option value="">Select Gender</option>
        <option value="Male">Male</option>
        <option value="Female">Female</option>
        </select>
        <span id="genderError" style="color:red;"></span><br><br>

        <label>Email:</label><br>
        <input type="email" id="email" name="email">
        <span id="emailError" style="color:red;"></span><br><br>

        <label>Religion:</label><br>
        <input type="text" id="religion" name="religion">
        <span id="religionError" style="color:red;"></span><br><br>

        <label>Nationality:</label><br>
        <input type="text" id="nationality" name="nationality">
        <span id="nationalityError" style="color:red;"></span><br><br>

        <label>Address:</label><br>
        <input type="text" id="address" name="address">
        <span id="addressError" style="color:red;"></span><br><br>

        <label>Civil Status:</label><br>
        <select id="civil_status" name="civil_status">
        <option value="">Select Civil Status</option>
        <option value="Single">Single</option>
        <option value="Married">Married</option>
        <option value="Legally Separated">Legally Separated</option>
        <option value="Widowed">Widowed</option>
        <option value="Annulled">Annulled</option>
        </select>
        <span id="civilStatusError" style="color:red;"></span><br><br>

        <input
        type="submit"
        value="Add User"
        class="add-btn">

    </form>
    </div>
</div>

<?php


$search = "";

if (isset($_GET["search"])) {
    $search = trim($_GET["search"]);
}

if ($search != "") {

    $stmt = $conn->prepare("
        SELECT *
        FROM names
        WHERE
            name LIKE ?
            OR email LIKE ?
            OR religion LIKE ?
            OR nationality LIKE ?
            OR address LIKE ?
    ");

    $searchTerm = "%$search%";

    $stmt->bind_param(
        "sssss",
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $searchTerm
    );

    $stmt->execute();

    $result = $stmt->get_result();

} else {

    $result = mysqli_query($conn, "SELECT * FROM names");

}
?>

<div class="card">

    <h2>Lists</h2>

    <form method="GET" class="search-form">

    <input
        type="text"
        name="search"
        placeholder="Search user..."
        value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">

    <button type="submit" class="search-btn">Search</button>

    <a href="dashboard.php" class="clear-btn">Clear</a>

</form>

    <form action="delete_selected.php" method="POST">

        <div class="list-actions">
            <input
                type="submit"
                class="delete-selected-btn"
                value="Delete Selected"
                onclick="return confirm('Delete all selected records?')">
        </div>


        <div class="table-container">

        <table class="user-table">
    <tr>

        <th><input type="checkbox" id="selectAll"></th>
        <th>ID</th>
        <th>Name</th>
        <th>Age</th>
        <th>Gender</th>
        <th>Email</th>
        <th>Religion</th>
        <th>Nationality</th>
        <th>Address</th>
        <th>Civil Status</th>
        <th>Actions</th>
    </tr>

<?php while ($row = $result->fetch_assoc()) { ?>

<tr>
    <td>
        <input
            type="checkbox"
            class="recordCheckbox"
            name="selected_ids[]"
            value="<?= $row['id']; ?>">
    </td>

    <td><?= $row['id']; ?></td>
    <td><?= htmlspecialchars($row['name']); ?></td>
    <td><?= htmlspecialchars($row['age']); ?></td>
    <td><?= htmlspecialchars($row['gender']); ?></td>
    <td><?= htmlspecialchars($row['email']); ?></td>
    <td><?= htmlspecialchars($row['religion']); ?></td>
    <td><?= htmlspecialchars($row['nationality']); ?></td>
    <td><?= htmlspecialchars($row['address']); ?></td>
    <td><?= htmlspecialchars($row['civil_status']); ?></td>
    <td>
        <a class="edit-btn" href="edit.php?id=<?= $row['id']; ?>">Edit</a>
        <a class="delete-btn"
           href="delete.php?id=<?= $row['id']; ?>"
           onclick="return confirm('Delete this record?')">
            Delete
        </a>
    </td>
</tr>


<?php } ?>

        </table>

    </form>


<br>




<script>
    const selectAllEl = document.getElementById("selectAll");
    const userFormEl = document.getElementById("userForm");
    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;


    selectAllEl.addEventListener("change", function () {
        let checkboxes = document.querySelectorAll(".recordCheckbox");
        checkboxes.forEach(function (checkbox) {
            checkbox.checked = selectAllEl.checked;
        });
    });

    if (userFormEl) {
        userFormEl.addEventListener("submit", function (event){
            event.preventDefault();
            let valid = true;

    // Clear previous errors
    document.getElementById("nameError").textContent = "";
    document.getElementById("ageError").textContent = "";
    document.getElementById("genderError").textContent = "";
    document.getElementById("emailError").textContent = "";
    document.getElementById("religionError").textContent = "";
    document.getElementById("nationalityError").textContent = "";
    document.getElementById("addressError").textContent = "";
    document.getElementById("civilStatusError").textContent = "";

    // Get values
    let name = document.getElementById("name").value.trim();
    let age = document.getElementById("age").value.trim();
    let gender = document.getElementById("gender").value;
    let email = document.getElementById("email").value.trim();
    let religion = document.getElementById("religion").value.trim();
    let nationality = document.getElementById("nationality").value.trim();
    let address = document.getElementById("address").value.trim();
    let civilStatus = document.getElementById("civil_status").value;

    // Name
    const namePattern = /^[A-Za-zÀ-ÿ' -]+$/;
    let nameParts = name.split(/\s+/);

    if (name === "") {
        document.getElementById("nameError").textContent = "Name is required.";
        valid = false;
    } else if (!namePattern.test(name)) {
        document.getElementById("nameError").textContent = "Name can only contain letters, spaces, apostrophes (') and hyphens (-).";
        valid = false;
    } else if (nameParts.length < 2) {
        document.getElementById("nameError").textContent = "Please enter your first and last name. Middle name is optional.";
        valid = false;
    }

    // Age
    if(age === ""){
        document.getElementById("ageError").textContent = "Age is required.";
        valid = false;
    } else if(isNaN(age) || age < 1 || age > 120){
        document.getElementById("ageError").textContent = "Age must be between 1 and 120.";
        valid = false;
    }

    // Gender
    if(gender === ""){
        document.getElementById("genderError").textContent = "Please select a gender.";
        valid = false;
    }

    // Email
    if(email === ""){
        document.getElementById("emailError").textContent = "Email is required.";
        valid = false;
    } else if(!emailPattern.test(email)){
        document.getElementById("emailError").textContent = "Invalid email address.";
        valid = false;
    }

    // Religion
    if(religion === ""){
        document.getElementById("religionError").textContent = "Religion is required.";
        valid = false;
    }

    // Nationality
    if(nationality === ""){
        document.getElementById("nationalityError").textContent = "Nationality is required.";
        valid = false;
    }

    // Address
    if(address === ""){
        document.getElementById("addressError").textContent = "Address is required.";
        valid = false;
    }

    // Civil Status
    if(civilStatus === ""){
        document.getElementById("civilStatusError").textContent = "Please select a civil status.";
        valid = false;
    }
        });
    }

    

    


window.addEventListener("beforeunload", function () {
    sessionStorage.setItem("scrollPosition", window.scrollY);
});

window.addEventListener("load", function () {
    const pos = sessionStorage.getItem("scrollPosition");
    if (pos !== null) {
        window.scrollTo(0, pos);
        sessionStorage.removeItem("scrollPosition");
    }
});
</script>

<?php include_once("inc/footer.php"); ?>