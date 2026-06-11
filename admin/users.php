<?php
//
// fichier admin/users.php
//

require_once 'includes/config.php';
require_once 'includes/auth.php';
requireRole('admin');

$currentUser = getCurrentUser();
$success = null;
$error = null;

// Ajout
if (isset($_POST['add'])) {
    try {
        $username  = trim($_POST['username']);
        $email     = trim($_POST['email']);
        $password  = $_POST['password'];
        $role      = in_array($_POST['role'], ['admin', 'member']) ? $_POST['role'] : 'member';
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

$pageTitle = 'Utilisateurs — Admin Nord Backgammon';
require_once 'includes/nav.php';
?>

<div class="container-fluid py-4 px-4">
    <div class="nb-page-header"><h1><i class="bi bi-people-fill me-2"></i>Gestion des utilisateurs</h1></div>

    <?php if ($success): ?>
    <div class="alert alert-success mb-4"><i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert alert-danger mb-4"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Formulaire de création -->
    <div class="card mb-4">
        <div class="card-header"><i class="bi bi-person-plus me-2"></i>Nouvel utilisateur</div>
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
                    <button type="submit" name="add" class="btn btn-primary">
                        <i class="bi bi-person-plus me-1"></i>Créer
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Liste -->
    <div class="card">
        <div class="card-header"><i class="bi bi-list-ul me-2"></i>Utilisateurs (<?= count($users) ?>)</div>
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th class="ps-3">Nom</th>
                        <th>Identifiant</th>
                        <th>Email</th>
                        <th>Rôle</th>
                        <th>Statut</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr <?= !$u['is_active'] ? 'style="opacity:.5"' : '' ?>>
                        <td class="ps-3"><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></td>
                        <td class="text-muted"><?= htmlspecialchars($u['username']) ?></td>
                        <td class="text-muted small"><?= htmlspecialchars($u['email']) ?></td>
                        <td>
                            <?php if ($u['id'] != $currentUser['id']): ?>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                <select name="role" class="form-select form-select-sm" style="width:auto;display:inline"
                                        onchange="this.form.submit()">
                                    <option value="admin"  <?= $u['role'] === 'admin'  ? 'selected' : '' ?>>Admin</option>
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
                        <td class="text-end pe-3">
                            <?php if ($u['id'] != $currentUser['id']): ?>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                <button type="submit" name="toggle_active"
                                        class="btn btn-sm <?= $u['is_active'] ? 'btn-outline-warning' : 'btn-outline-success' ?> me-1"
                                        title="<?= $u['is_active'] ? 'Désactiver' : 'Activer' ?>">
                                    <i class="bi bi-<?= $u['is_active'] ? 'person-dash' : 'person-check' ?>"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                            <button class="btn btn-sm btn-outline-secondary"
                                    onclick="resetPassword(<?= $u['id'] ?>, '<?= htmlspecialchars($u['username']) ?>')"
                                    title="Réinitialiser le mot de passe">
                                <i class="bi bi-key"></i>
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
        <div class="modal-content" style="background:#2a2a2a;border-color:#444">
            <div class="modal-header" style="border-color:#444">
                <h5 class="modal-title"><i class="bi bi-key me-2"></i>Réinitialiser le mot de passe</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="post">
                    <input type="hidden" name="id" id="pwd_user_id">
                    <p class="text-muted small mb-3">Utilisateur : <strong id="pwd_username"></strong></p>
                    <div class="mb-3">
                        <label class="form-label">Nouveau mot de passe <small class="text-muted">(8 car. min)</small></label>
                        <input type="password" name="new_password" class="form-control" required minlength="8">
                    </div>
                    <button type="submit" name="reset_password" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i>Enregistrer
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$extraScript = <<<'JS'
<script>
function resetPassword(id, username) {
    document.getElementById('pwd_user_id').value = id;
    document.getElementById('pwd_username').textContent = username;
    new bootstrap.Modal(document.getElementById('passwordModal')).show();
}
</script>
JS;
require_once 'includes/admin_footer.php';
?>
