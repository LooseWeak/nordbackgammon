<?php
$pageTitle = 'Contact — Nord Backgammon';
require_once 'admin/includes/config.php';
require_once 'includes/header.php';

$success = false;
$error = null;
$form = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = [
        'name'    => trim($_POST['name'] ?? ''),
        'email'   => trim($_POST['email'] ?? ''),
        'subject' => trim($_POST['subject'] ?? ''),
        'message' => trim($_POST['message'] ?? ''),
    ];

    if ($form['name'] && $form['email'] && $form['subject'] && $form['message']) {
        if (filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)");
                $stmt->execute([$form['name'], $form['email'], $form['subject'], $form['message']]);
                $success = true;
                $form = [];
            } catch (PDOException $e) {
                $error = "Une erreur est survenue. Veuillez réessayer.";
            }
        } else {
            $error = "L'adresse email n'est pas valide.";
        }
    } else {
        $error = "Tous les champs sont obligatoires.";
    }
}
?>

<section class="nb-section">
    <div class="container">
        <div class="text-center mb-5">
            <h1 class="section-title">Nous <span>contacter</span></h1>
            <div class="section-divider"></div>
            <p class="text-muted">Une question, envie de rejoindre le club ? Écrivez-nous.</p>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-7">
                <?php if ($success): ?>
                    <div class="nb-card p-5 text-center">
                        <i class="bi bi-check-circle-fill text-success" style="font-size:3rem"></i>
                        <h2 class="h4 mt-3 mb-2">Message envoyé !</h2>
                        <p class="text-muted">Merci pour votre message. Nous vous répondrons dans les meilleurs délais.</p>
                        <a href="/" class="btn btn-nb mt-2">Retour à l'accueil</a>
                    </div>
                <?php else: ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger mb-4"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    <div class="nb-card">
                        <div class="card-body p-4 p-md-5">
                            <form method="post" class="nb-form">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Nom complet *</label>
                                        <input type="text" name="name" class="form-control" required
                                               value="<?= htmlspecialchars($form['name'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Email *</label>
                                        <input type="email" name="email" class="form-control" required
                                               value="<?= htmlspecialchars($form['email'] ?? '') ?>">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Sujet *</label>
                                        <input type="text" name="subject" class="form-control" required
                                               value="<?= htmlspecialchars($form['subject'] ?? '') ?>">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Message *</label>
                                        <textarea name="message" class="form-control" rows="6" required><?= htmlspecialchars($form['message'] ?? '') ?></textarea>
                                    </div>
                                    <div class="col-12 text-end">
                                        <button type="submit" class="btn btn-nb">
                                            <i class="bi bi-send me-1"></i> Envoyer
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="col-lg-4 offset-lg-1 mt-4 mt-lg-0">
                <div class="nb-card p-4 mb-3">
                    <i class="bi bi-facebook pilier-icon" style="font-size:1.8rem"></i>
                    <h3 class="h6 fw-bold mb-1">Groupe Facebook</h3>
                    <p class="text-muted small mb-2">Rejoignez notre communauté pour suivre nos événements.</p>
                    <a href="https://www.facebook.com/groups/nordbackgammonlille" target="_blank" rel="noopener" class="btn btn-outline-nb btn-sm">Rejoindre le groupe</a>
                </div>
                <div class="nb-card p-4">
                    <i class="bi bi-geo-alt-fill pilier-icon" style="font-size:1.8rem"></i>
                    <h3 class="h6 fw-bold mb-1">Région lilloise</h3>
                    <p class="text-muted small">Nos soirées se tiennent à Lille et environs. Contactez-nous pour les détails du prochain rendez-vous.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
