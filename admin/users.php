<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
requireRole('admin');
require_once 'includes/nav.php';

$currentUser = getCurrentUser();
$success = null;
$error = null;

// Ajout
if (isset($_POST['add'])) {
    try {
        $username = trim($_POST['username']);
        $email    = trim($_POST['email']);
        $password = $_POST['password'];
        $role     = in_array($_POST['role'], ['admin', 'member']) ? $_POST['role'] : 'member';
        $firstName = trim($_POST['first_name']);
        $lastName  = trim($_POST['last_name']);

        if (!$username || !$email || !$password) throw new Exception("Tous les champs obligatoires doivent être remplis.");
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception("Email invalide.");
        if (strlen($password) < 8) throw new Exception("Le mot de passe doit faire au moins 8 caractères.");

        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $pdo->prepare("INSERT INTO users (username, email, password_hash, role, first_name, last_name) VALUES (?,?,?,?,?,?)")
            ->execute([$username, $email, $hash, $role, $firstName, $lastName]);
        $success = "Utilisateur créé.";
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Toggle actif
if (isset($_POST['toggle_active'], $_POST['id'])) {
    if ($_POST['id'] != $currentUser['id']) {
        $stmt = $pdo->prepare("SELECT is_active FROM users WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        $current = $stmt->fetchColumn();
        $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?")
            ->execute([$current ? 0 : 1, $_POST['id']]);
        $success = "Statut mis à jour.";
    }
}

// Changement de rôle
if (isset($_POST['change_role'], $_POST['id'], $_POST['role'])) {
    if ($_POST['id'] != $currentUser['id']) {
        $role = in_array($_POST['role'], ['admin', 'member']) ? $_POST['role'] : 'member';
        $pdo->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$role, $_POST['id']]);
        $success = "Rôle mis à jour.";
    }
}

// Réinitialisation du mot de passe
if (isset($_POST['reset_password'], $_POST['id'], $_POST['new_password'])) {
    try {
        $password = $_POST['new_password'];
        if (strlen($password) < 8) throw new Exception("Le mot de passe doit faire au moins 8 caractères.");
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$hash, $_POST['id']]);
        $success = "Mot de passe réinitialisé.";
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Utilisateurs — Admin Nord Backgammon</title>
</head>
<body>
<div class="container-fluid py-4 px-4">
    <h1 class="h4 fw-bold mb-4" style="color:#E87128">Gestion des utilisateurs</h1>

    <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <!-- Formulaire de création -->
    <div class="card mb-4">
        <div class="card-header fw-bold">Nouvel utilisateur</div>
        <div class="card-body">
            <form method="post" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Prénom</label>
                    <input type="text" name="first_name" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Nom</label>
                    <input type="text" name="last_name" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Identifiant *</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Email *</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Mot de passe * <small class="text-muted">(8 car. min)</small></label>
                    <input type="password" name="password" class="form-control" required minlength="8">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Rôle</label>
                    <select name="role" class="form-select">
                        <option value="member">Membre</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" name="add" class="btn btn-primary">Créer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Liste -->
    <div class="card">
        <div class="card-header fw-bold">Utilisateurs (<?= count($users) ?>)</div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th class="ps-3">Nom</th>
                        <th>Identifiant</th>
                        <th>Email</th>
                        <th>Rôle</th>
                        <th>Statut</th>
                        <th class="pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr <?= !$u['is_active'] ? 'style="opacity:.5"' : '' ?>>
                        <td class="ps-3"><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></td>
                        <td><?= htmlspecialchars($u['username']) ?></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td>
                            <?php if ($u['id'] != $currentUser['id']): ?>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                <select name="role" class="form-select form-select-sm" style="width:auto;display:inline"
                                        onchange="this.form.submit()">
                                    <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                    <option value="member" <?= $u['role'] === 'member' ? 'selected' : '' ?>>Membre</option>
                                </select>
                                <input type="hidden" name="change_role" value="1">
                            </form>
                            <?php else: ?>
                                <span class="badge" style="background:#E87128"><?= $u['role'] ?></span>
                                <small class="text-muted">(vous)</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($u['is_active']): ?>
                                <span class="badge" style="background:#198754">Actif</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inactif</span>
                            <?php endif; ?>
                        </td>
                        <td class="pe-3">
                            <?php if ($u['id'] != $currentUser['id']): ?>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                <button type="submit" name="toggle_active" class="btn btn-sm btn-outline-secondary">
                                    <?= $u['is_active'] ? 'Désactiver' : 'Activer' ?>
                                </button>
                            </form>
                            <?php endif; ?>
                            <button class="btn btn-sm btn-outline-warning"
                                    onclick="resetPassword(<?= $u['id'] ?>, '<?= htmlspecialchars($u['username']) ?>')">
                                Mot de passe
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal réinitialisation MDP -->
<div class="modal fade" id="passwordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Réinitialiser le mot de passe</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="post">
                    <input type="hidden" name="id" id="pwd_user_id">
                    <p class="text-muted small mb-3">Utilisateur : <strong id="pwd_username"></strong></p>
                    <div class="mb-3">
                        <label class="form-label">Nouveau mot de passe (8 car. min)</label>
                        <input type="password" name="new_password" class="form-control" required minlength="8">
                    </div>
                    <button type="submit" name="reset_password" class="btn btn-primary">Enregistrer</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function resetPassword(id, username) {
    document.getElementById('pwd_user_id').value = id;
    document.getElementById('pwd_username').textContent = username;
    new bootstrap.Modal(document.getElementById('passwordModal')).show();
}
</script>
</body>
</html>
