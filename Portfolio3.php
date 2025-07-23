<?php
session_start();

$pdo = new PDO("mysql:host=localhost;dbname=vkitchen;charset=utf8mb4", "root", "");


if (isset($_POST['register'])) {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $errors = [];

    if (!$username) $errors[] = "Username needed";
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Email not valid";
    if (strlen($password) < 8) $errors[] = "Password too short";
    if ($password !== $password_confirm) $errors[] = "Passwords mismatch";

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username=? OR email=?");
    $stmt->execute([$username, $email]);
    if ($stmt->fetchColumn() > 0) $errors[] = "Username or email taken";

    if (!$errors) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username,email,password) VALUES (?,?,?)");
        $stmt->execute([$username,$email,$hash]);
        $_SESSION['user_id'] = $pdo->lastInsertId();
        header("Location: index.php");
        exit;
    }
}

if (isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $errors = [];

    if (!$username || !$password) $errors[] = "Fill both fields";
    else {
        $stmt = $pdo->prepare("SELECT uid,password FROM users WHERE username=?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['uid'];
            header("Location: index.php");
            exit;
        } else {
            $errors[] = "Wrong login data";
        }
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}

function loggedIn() {
    return isset($_SESSION['user_id']);
}

function currentUser() {
    return $_SESSION['user_id'] ?? null;
}

if (isset($_POST['recipe_save']) && loggedIn()) {
    $name = trim($_POST['name'] ?? '');
    $type = $_POST['type'] ?? '';
    $desc = trim($_POST['description'] ?? '');
    $ing = trim($_POST['ingredients'] ?? '');
    $inst = trim($_POST['instructions'] ?? '');
    $time = (int)($_POST['cooking_time'] ?? 0);
    $errors = [];

    $valid_types = ['French','Italian','Chinese','Indian','Mexican','Others'];
    if (!$name) $errors[] = "Name missing";
    if (!in_array($type, $valid_types)) $errors[] = "Type wrong";
    if (!$desc) $errors[] = "No description";
    if (!$ing) $errors[] = "No ingredients";
    if (!$inst) $errors[] = "No instructions";
    if ($time < 1) $errors[] = "Time must be positive";

    $imagePath = null;

    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg','image/png','image/gif'];
        if (!in_array($_FILES['image']['type'], $allowed)) {
            $errors[] = "Bad image type";
        } else {
            $dir = __DIR__.'/uploads/';
            if (!is_dir($dir)) mkdir($dir);
            $filename = uniqid() . '-' . basename($_FILES['image']['name']);
            $target = $dir . $filename;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
                $imagePath = 'uploads/'.$filename;
            } else {
                $errors[] = "Can't upload image";
            }
        }
    }

    if (!$errors) {
        if (!empty($_POST['recipe_id'])) {
            $stmt = $pdo->prepare("SELECT uid FROM recipes WHERE rid=?");
            $stmt->execute([$_POST['recipe_id']]);
            $owner = $stmt->fetchColumn();
            if ($owner != currentUser()) die('Not allowed');

            $sql = "UPDATE recipes SET name=?, type=?, description=?, ingredients=?, instructions=?, cookingtime=?";
            $params = [$name,$type,$desc,$ing,$inst,$time];
            if ($imagePath) {
                $sql .= ", image=?";
                $params[] = $imagePath;
            }
            $sql .= " WHERE rid=?";
            $params[] = $_POST['recipe_id'];
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        } else {
            $stmt = $pdo->prepare("INSERT INTO recipes (name,type,description,ingredients,instructions,cookingtime,image,uid) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->execute([$name,$type,$desc,$ing,$inst,$time,$imagePath,currentUser()]);
        }
        header("Location: index.php");
        exit;
    }
}

if (isset($_GET['delete']) && loggedIn()) {
    $rid = (int)$_GET['delete'];
    $stmt = $pdo->prepare("SELECT uid FROM recipes WHERE rid=?");
    $stmt->execute([$rid]);
    $owner = $stmt->fetchColumn();
    if ($owner == currentUser()) {
        $stmt = $pdo->prepare("DELETE FROM recipes WHERE rid=?");
        $stmt->execute([$rid]);
    }
    header("Location: index.php");
    exit;
}

$search_name = $_GET['search_name'] ?? '';
$search_type = $_GET['search_type'] ?? '';

$sql = "SELECT recipes.*, users.username FROM recipes JOIN users ON recipes.uid=users.uid WHERE 1";
$params = [];

if ($search_name) {
    $sql .= " AND recipes.name LIKE ?";
    $params[] = "%$search_name%";
}

if ($search_type && in_array($search_type, ['French','Italian','Chinese','Indian','Mexican','Others'])) {
    $sql .= " AND recipes.type=?";
    $params[] = $search_type;
}

$sql .= " ORDER BY rid DESC LIMIT 50";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$recipes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head><title>Recipes</title></head>
<body>

<nav>
    <a href="index.php">Home</a>
    <?php if(loggedIn()): ?>
        <a href="?action=create">Add Recipe</a>
        <a href="?logout=1">Logout</a>
    <?php else: ?>
        <a href="?action=login">Login</a>
        <a href="?action=register">Register</a>
    <?php endif; ?>
</nav>

<hr>

<?php
if (!empty($errors)) {
    echo "<ul style='color:red;'>";
    foreach($errors as $e) echo "<li>".htmlspecialchars($e)."</li>";
    echo "</ul>";
}

$action = $_GET['action'] ?? '';

if ($action == 'register' && !loggedIn()) { ?>
    <h2>Register</h2>
    <form method="post">
        Username: <input name="username" required><br>
        Email: <input name="email" type="email" required><br>
        Password: <input name="password" type="password" required><br>
        Confirm: <input name="password_confirm" type="password" required><br>
        <button name="register" type="submit">Register</button>
    </form>
<?php } elseif ($action == 'login' && !loggedIn()) { ?>
    <h2>Login</h2>
    <form method="post">
        Username: <input name="username" required><br>
        Password: <input name="password" type="password" required><br>
        <button name="login" type="submit">Login</button>
    </form>
<?php } elseif (($action == 'create' || $action == 'edit') && loggedIn()) {
    $editing = false;
    $recipe = ['rid'=>'', 'name'=>'', 'type'=>'', 'description'=>'', 'ingredients'=>'', 'instructions'=>'', 'cookingtime'=>'', 'image'=>''];
    if ($action == 'edit' && isset($_GET['id'])) {
        $stmt = $pdo->prepare("SELECT * FROM recipes WHERE rid=?");
        $stmt->execute([$_GET['id']]);
        $recipe = $stmt->fetch();
        if (!$recipe) { echo "Recipe not found"; exit; }
        if ($recipe['uid'] != currentUser()) { echo "Not your recipe"; exit; }
        $editing = true;
    }
    ?>
    <h2><?= $editing ? 'Edit' : 'Add' ?> Recipe</h2>
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="recipe_id" value="<?= htmlspecialchars($recipe['rid']) ?>">
        Name: <input name="name" required value="<?= htmlspecialchars($recipe['name']) ?>"><br>
        Type: <select name="type" required>
            <?php
            $types = ['French','Italian','Chinese','Indian','Mexican','Others'];
            foreach ($types as $t) {
                $sel = ($recipe['type'] == $t) ? 'selected' : '';
                echo "<option $sel>$t</option>";
            }
            ?>
        </select><br>
        Description:<br>
        <textarea name="description" required><?= htmlspecialchars($recipe['description']) ?></textarea><br>
        Ingredients:<br>
        <textarea name="ingredients" required><?= htmlspecialchars($recipe['ingredients']) ?></textarea><br>
        Instructions:<br>
        <textarea name="instructions" required><?= htmlspecialchars($recipe['instructions']) ?></textarea><br>
        Cooking time (minutes): <input name="cooking_time" type="number" min="1" required value="<?= htmlspecialchars($recipe['cookingtime']) ?>"><br>
        Image: <input type="file" name="image"><br>
        <?php if ($recipe['image']): ?>
            <img src="<?= htmlspecialchars($recipe['image']) ?>" style="max-width:150px"><br>
        <?php endif; ?>
        <button name="recipe_save" type="submit"><?= $editing ? 'Update' : 'Add' ?></button>
    </form>

<?php } elseif (isset($_GET['view'])) {
    $rid = (int)$_GET['view'];
    $stmt = $pdo->prepare("SELECT recipes.*, users.username FROM recipes JOIN users ON recipes.uid=users.uid WHERE rid=?");
    $stmt->execute([$rid]);
    $r = $stmt->fetch();
    if (!$r) {
        echo "Recipe not found";
        exit;
    }
    ?>
    <h2><?= htmlspecialchars($r['name']) ?></h2>
    <p>Type: <?= htmlspecialchars($r['type']) ?></p>
    <p>Description:<br><?= nl2br(htmlspecialchars($r['description'])) ?></p>
    <p>Ingredients:<br><?= nl2br(htmlspecialchars($r['ingredients'])) ?></p>
    <p>Instructions:<br><?= nl2br(htmlspecialchars($r['instructions'])) ?></p>
    <p>Cooking time: <?= htmlspecialchars($r['cookingtime']) ?> min</p>
    <p>Owner: <?= htmlspecialchars($r['username']) ?></p>
    <?php if ($r['image']): ?>
        <img src="<?= htmlspecialchars($r['image']) ?>" style="max-width:300px">
    <?php endif; ?>
    <?php if (loggedIn() && $r['uid'] == currentUser()): ?>
        <p>
            <a href="?action=edit&id=<?= $r['rid'] ?>">Edit</a> |
            <a href="?delete=<?= $r['rid'] ?>" onclick="return confirm('Delete?')">Delete</a>
        </p>
    <?php endif; ?>

<?php } else { ?>
    <h1>Recipes</h1>
    <form method="get">
        <input name="search_name" placeholder="Search name" value="<?= htmlspecialchars($search_name) ?>">
        <select name="search_type">
            <option value="">All types</option>
            <?php foreach(['French','Italian','Chinese','Indian','Mexican','Others'] as $t): ?>
                <option value="<?= $t ?>" <?= $search_type == $t ? 'selected' : '' ?>><?= $t ?></option>
            <?php endforeach; ?>
        </select>
        <button>Search</button>
    </form>

    <?php if (!$recipes) echo "<p>No recipes found.</p>"; ?>
    <?php foreach($recipes as $r): ?>
        <div style="border:1px solid #ccc; margin-bottom:10px; padding:10px;">
            <h3><a href="?view=<?= $r['rid'] ?>"><?= htmlspecialchars($r['name']) ?></a></h3>
            <p>Type: <?= htmlspecialchars($r['type']) ?></p>
            <p>Owner: <?= htmlspecialchars($r['username']) ?></p>
            <?php if ($r['image']): ?>
                <img src="<?= htmlspecialchars($r['image']) ?>" style="max-width:150px"><br>
            <?php endif; ?>
            <?php if (loggedIn() && $r['uid'] == currentUser()): ?>
                <a href="?action=edit&id=<?= $r['rid'] ?>">Edit</a> |
                <a href="?delete=<?= $r['rid'] ?>" onclick="return confirm('Delete?')">Delete</a>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

<?php } ?>

</body>
</html>
