-- ============================================================
-- Sophishticated - Seed Data
-- ============================================================

USE sophishticated;

-- ============================================================
-- 1. Admin user
-- ============================================================
INSERT INTO users (username, email, password, role, is_active, must_change_password, created_at, updated_at)
VALUES (
    'admin',
    'admin@example.com',
    '$2y$12$LJ3m4ys3Gm7NBVZ2YCuaOeSTKx3l3xZT8aFyNmLxPmRCIUxEKdWi2',
    'admin',
    1,
    1,
    NOW(),
    NOW()
);

-- ============================================================
-- 2. Default groups
-- ============================================================

-- Team groups
INSERT INTO `groups` (name, description, type, color) VALUES
('Direction',    'Équipe de direction générale',                      'team', '#1B5E20'),
('RH',           'Ressources humaines',                               'team', '#0D47A1'),
('Commerce',     'Équipe commerciale et relation client',             'team', '#E65100'),
('Production',   'Équipe de production et opérations',                'team', '#4E342E'),
('Logistique',   'Équipe logistique et supply chain',                 'team', '#37474F'),
('Comptabilité', 'Équipe comptabilité et finances',                   'team', '#4A148C'),
('IT',           'Équipe informatique et systèmes d''information',    'team', '#01579B');

-- Category groups
INSERT INTO `groups` (name, description, type, color) VALUES
('Cadres',       'Personnel cadre',                                   'category', '#2E7D32'),
('Non-cadres',   'Personnel non-cadre',                               'category', '#F57F17'),
('Intérimaires', 'Personnel intérimaire et contrats temporaires',     'category', '#AD1457');

-- ============================================================
-- 3. Landing pages
-- ============================================================

-- 3a. Fake Microsoft 365 login page
INSERT INTO landing_pages (name, description, html_content, capture_fields, redirect_url, awareness_html, is_active, created_by)
VALUES (
    'Microsoft 365 - Page de connexion',
    'Fausse page de connexion Microsoft 365 réaliste',
    '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Se connecter - Microsoft 365</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: "Segoe UI", -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: #f2f2f2;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .login-container {
            background: #ffffff;
            padding: 44px;
            min-width: 440px;
            max-width: 440px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.2);
        }
        .logo {
            margin-bottom: 16px;
        }
        .logo svg {
            width: 108px;
            height: 24px;
        }
        h1 {
            font-size: 24px;
            font-weight: 600;
            color: #1b1b1b;
            margin-bottom: 12px;
        }
        .subtitle {
            font-size: 13px;
            color: #1b1b1b;
            margin-bottom: 24px;
        }
        .form-group {
            margin-bottom: 16px;
        }
        .form-group label {
            display: none;
        }
        .form-group input {
            width: 100%;
            padding: 6px 10px;
            font-size: 15px;
            border: none;
            border-bottom: 1px solid #666666;
            outline: none;
            background: transparent;
            color: #1b1b1b;
            font-family: "Segoe UI", sans-serif;
        }
        .form-group input:focus {
            border-bottom-color: #0067b8;
            border-bottom-width: 2px;
        }
        .form-group input::placeholder {
            color: #666666;
        }
        .error-msg {
            display: none;
            color: #e81123;
            font-size: 13px;
            margin-top: 8px;
        }
        .forgot-link {
            display: block;
            font-size: 13px;
            color: #0067b8;
            text-decoration: none;
            margin-bottom: 24px;
        }
        .forgot-link:hover {
            text-decoration: underline;
            color: #004578;
        }
        .btn-submit {
            width: 100%;
            padding: 10px 20px;
            background-color: #0067b8;
            color: #ffffff;
            border: none;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            font-family: "Segoe UI", sans-serif;
        }
        .btn-submit:hover {
            background-color: #005a9e;
        }
        .footer-links {
            margin-top: 24px;
            font-size: 13px;
            color: #666666;
        }
        .footer-links a {
            color: #0067b8;
            text-decoration: none;
        }
        .footer-links a:hover {
            text-decoration: underline;
        }
        .bg-banner {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: 40px;
            background: #f2f2f2;
            text-align: center;
            padding: 10px;
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 108 24">
                <text x="0" y="18" font-family="Segoe UI, sans-serif" font-size="18" font-weight="600" fill="#737373">Microsoft</text>
            </svg>
        </div>
        <h1>Connexion</h1>
        <p class="subtitle">Accédez à votre espace Microsoft 365</p>
        <form id="phish-form" method="POST">
            <div class="form-group">
                <label for="email">Adresse e-mail</label>
                <input type="email" id="email" name="email" placeholder="E-mail, téléphone ou Skype" required autocomplete="username">
            </div>
            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password" placeholder="Mot de passe" required autocomplete="current-password">
            </div>
            <p class="error-msg" id="error-msg">Votre compte ou mot de passe est incorrect.</p>
            <a href="#" class="forgot-link">Mot de passe oublié ?</a>
            <button type="submit" class="btn-submit">Se connecter</button>
        </form>
        <div class="footer-links">
            Pas de compte ? <a href="#">Créez-en un !</a>
        </div>
    </div>
    <div class="bg-banner">© Microsoft 2026</div>
</body>
</html>',
    '["email", "password"]',
    '/awareness',
    '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sensibilisation au phishing - your organization</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: "Segoe UI", -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: #f5f9f5;
            color: #333;
            line-height: 1.6;
        }
        .container {
            max-width: 680px;
            margin: 40px auto;
            padding: 40px;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        }
        .header {
            text-align: center;
            margin-bottom: 32px;
            padding-bottom: 24px;
            border-bottom: 3px solid #2E7D32;
        }
        .header h1 {
            font-size: 26px;
            color: #2E7D32;
            margin-bottom: 8px;
        }
        .header .brand {
            font-size: 14px;
            color: #666;
        }
        .icon-shield {
            font-size: 48px;
            text-align: center;
            margin-bottom: 16px;
        }
        .alert-box {
            background: #FFF3E0;
            border-left: 4px solid #E65100;
            padding: 16px 20px;
            border-radius: 4px;
            margin-bottom: 24px;
        }
        .alert-box h2 {
            color: #E65100;
            font-size: 18px;
            margin-bottom: 8px;
        }
        .section {
            margin-bottom: 24px;
        }
        .section h3 {
            color: #2E7D32;
            font-size: 18px;
            margin-bottom: 12px;
        }
        .section ul {
            padding-left: 24px;
        }
        .section ul li {
            margin-bottom: 8px;
        }
        .tips-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-top: 16px;
        }
        .tip-card {
            background: #E8F5E9;
            padding: 16px;
            border-radius: 8px;
        }
        .tip-card strong {
            display: block;
            color: #1B5E20;
            margin-bottom: 4px;
        }
        .tip-card p {
            font-size: 14px;
            color: #333;
        }
        .reassurance {
            background: #E8F5E9;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            margin-top: 24px;
        }
        .reassurance p {
            font-size: 15px;
            color: #2E7D32;
            font-weight: 500;
        }
        .footer {
            text-align: center;
            margin-top: 32px;
            font-size: 13px;
            color: #888;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="icon-shield">&#x1F6E1;&#xFE0F;</div>
            <h1>Exercice de sensibilisation au phishing</h1>
            <div class="brand">your organization - Service Informatique</div>
        </div>

        <div class="alert-box">
            <h2>Pas de panique, c''est un exercice !</h2>
            <p>La page que vous venez de visiter était une <strong>simulation de phishing</strong> organisée par votre entreprise. Aucune de vos informations n''a été transmise ou enregistrée de manière malveillante.</p>
        </div>

        <div class="section">
            <h3>Que s''est-il passé ?</h3>
            <p>Vous avez cliqué sur un lien dans un e-mail simulé et avez interagi avec une fausse page de connexion Microsoft 365. Dans une attaque réelle, vos identifiants auraient pu être volés par un attaquant.</p>
        </div>

        <div class="section">
            <h3>Comment repérer ce type d''attaque ?</h3>
            <div class="tips-grid">
                <div class="tip-card">
                    <strong>Vérifiez l''URL</strong>
                    <p>La vraie page Microsoft utilise le domaine login.microsoftonline.com. Vérifiez toujours l''adresse dans la barre du navigateur.</p>
                </div>
                <div class="tip-card">
                    <strong>Méfiez-vous de l''urgence</strong>
                    <p>Les e-mails de phishing créent souvent un sentiment d''urgence pour vous pousser à agir sans réfléchir.</p>
                </div>
                <div class="tip-card">
                    <strong>Vérifiez l''expéditeur</strong>
                    <p>Contrôlez l''adresse e-mail de l''expéditeur, pas seulement le nom affiché. Les domaines suspects sont un signal d''alerte.</p>
                </div>
                <div class="tip-card">
                    <strong>En cas de doute</strong>
                    <p>Contactez le service IT directement. Ne répondez jamais à un e-mail suspect et ne cliquez pas sur ses liens.</p>
                </div>
            </div>
        </div>

        <div class="reassurance">
            <p>Cet exercice est strictement confidentiel et bienveillant. Il vise à renforcer la sécurité de tous. Merci pour votre participation !</p>
        </div>

        <div class="footer">
            <p>your organization - Programme de sensibilisation à la cybersécurité</p>
        </div>
    </div>
</body>
</html>',
    1,
    1
);

-- 3b. Fake generic login page
INSERT INTO landing_pages (name, description, html_content, capture_fields, redirect_url, awareness_html, is_active, created_by)
VALUES (
    'Portail générique - Page de connexion',
    'Fausse page de connexion générique pour simulation',
    '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion sécurisée</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .card {
            background: #fff;
            border-radius: 12px;
            padding: 48px 40px;
            width: 400px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .card h1 {
            font-size: 24px;
            color: #333;
            margin-bottom: 8px;
            text-align: center;
        }
        .card p.sub {
            font-size: 14px;
            color: #888;
            text-align: center;
            margin-bottom: 32px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #555;
            margin-bottom: 6px;
        }
        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 15px;
            transition: border-color 0.2s;
            outline: none;
        }
        .form-group input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.15);
        }
        .btn-login {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: opacity 0.2s;
        }
        .btn-login:hover {
            opacity: 0.9;
        }
        .extras {
            text-align: center;
            margin-top: 20px;
            font-size: 13px;
        }
        .extras a {
            color: #667eea;
            text-decoration: none;
        }
        .lock-icon {
            text-align: center;
            font-size: 40px;
            margin-bottom: 16px;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="lock-icon">&#x1F512;</div>
        <h1>Connexion sécurisée</h1>
        <p class="sub">Veuillez vous identifier pour accéder à votre espace</p>
        <form id="phish-form" method="POST">
            <div class="form-group">
                <label for="email">Adresse e-mail</label>
                <input type="email" id="email" name="email" placeholder="votre@email.com" required>
            </div>
            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password" placeholder="Votre mot de passe" required>
            </div>
            <button type="submit" class="btn-login">Se connecter</button>
        </form>
        <div class="extras">
            <a href="#">Mot de passe oublié ?</a>
        </div>
    </div>
</body>
</html>',
    '["email", "password"]',
    '/awareness',
    '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sensibilisation au phishing - your organization</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #f5f9f5;
            color: #333;
            line-height: 1.6;
        }
        .container {
            max-width: 680px;
            margin: 40px auto;
            padding: 40px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        }
        .header {
            text-align: center;
            margin-bottom: 32px;
            padding-bottom: 24px;
            border-bottom: 3px solid #2E7D32;
        }
        .header h1 {
            font-size: 26px;
            color: #2E7D32;
            margin-bottom: 8px;
        }
        .header .brand {
            font-size: 14px;
            color: #666;
        }
        .icon-area {
            font-size: 48px;
            text-align: center;
            margin-bottom: 16px;
        }
        .alert-box {
            background: #FFF3E0;
            border-left: 4px solid #E65100;
            padding: 16px 20px;
            border-radius: 4px;
            margin-bottom: 24px;
        }
        .alert-box h2 {
            color: #E65100;
            font-size: 18px;
            margin-bottom: 8px;
        }
        .section {
            margin-bottom: 24px;
        }
        .section h3 {
            color: #2E7D32;
            font-size: 18px;
            margin-bottom: 12px;
        }
        .section ul {
            padding-left: 24px;
        }
        .section ul li {
            margin-bottom: 8px;
        }
        .reassurance {
            background: #E8F5E9;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            margin-top: 24px;
        }
        .reassurance p {
            font-size: 15px;
            color: #2E7D32;
            font-weight: 500;
        }
        .footer {
            text-align: center;
            margin-top: 32px;
            font-size: 13px;
            color: #888;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="icon-area">&#x1F6E1;&#xFE0F;</div>
            <h1>C''était un exercice de phishing !</h1>
            <div class="brand">your organization - Service Informatique</div>
        </div>

        <div class="alert-box">
            <h2>Rassurez-vous, tout va bien</h2>
            <p>Cette page de connexion était factice, créée dans le cadre d''un exercice de sensibilisation. <strong>Aucune donnée n''a été compromise.</strong></p>
        </div>

        <div class="section">
            <h3>Les bons réflexes à adopter</h3>
            <ul>
                <li><strong>Ne saisissez jamais vos identifiants</strong> sur une page atteinte via un lien dans un e-mail inattendu.</li>
                <li><strong>Accédez toujours à vos services</strong> en tapant directement l''adresse dans votre navigateur ou via vos favoris.</li>
                <li><strong>Vérifiez le certificat SSL</strong> (cadenas dans la barre d''adresse) et le nom de domaine exact.</li>
                <li><strong>Signalez les e-mails suspects</strong> au service informatique de l''entreprise.</li>
            </ul>
        </div>

        <div class="section">
            <h3>Pourquoi cet exercice ?</h3>
            <p>Le phishing est la menace n°1 en cybersécurité. Ces simulations régulières nous permettent de renforcer collectivement notre vigilance et de protéger l''ensemble de l''entreprise.</p>
        </div>

        <div class="reassurance">
            <p>Cet exercice est anonyme et bienveillant. Il n''a aucun impact sur votre évaluation professionnelle. Merci de votre participation !</p>
        </div>

        <div class="footer">
            <p>your organization - Programme de sensibilisation à la cybersécurité</p>
        </div>
    </div>
</body>
</html>',
    1,
    1
);

-- 3c. Fake HR form page
INSERT INTO landing_pages (name, description, html_content, capture_fields, redirect_url, awareness_html, is_active, created_by)
VALUES (
    'Formulaire RH - Mise à jour des informations',
    'Fausse page de formulaire RH demandant des informations sensibles',
    '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mise à jour - Portail RH</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #f0f2f5;
            min-height: 100vh;
        }
        .topbar {
            background: #1a237e;
            color: #fff;
            padding: 14px 32px;
            font-size: 16px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .topbar .logo-text {
            font-size: 18px;
        }
        .main {
            max-width: 600px;
            margin: 40px auto;
            padding: 0 20px;
        }
        .card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.12);
            padding: 36px 40px;
        }
        .card h1 {
            font-size: 22px;
            color: #1a237e;
            margin-bottom: 8px;
        }
        .card .desc {
            font-size: 14px;
            color: #666;
            margin-bottom: 28px;
            line-height: 1.5;
        }
        .field {
            margin-bottom: 20px;
        }
        .field label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #333;
            margin-bottom: 6px;
        }
        .field label .required {
            color: #d32f2f;
        }
        .field input {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 15px;
            outline: none;
            transition: border-color 0.2s;
        }
        .field input:focus {
            border-color: #1a237e;
            box-shadow: 0 0 0 3px rgba(26,35,126,0.1);
        }
        .field .hint {
            font-size: 12px;
            color: #999;
            margin-top: 4px;
        }
        .row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .btn-submit {
            width: 100%;
            padding: 14px;
            background: #1a237e;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 8px;
        }
        .btn-submit:hover {
            background: #283593;
        }
        .notice {
            font-size: 12px;
            color: #999;
            margin-top: 16px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="topbar">
        <span class="logo-text">Portail RH - Espace Collaborateur</span>
    </div>
    <div class="main">
        <div class="card">
            <h1>Mise à jour de vos informations</h1>
            <p class="desc">Suite à la mise en conformité RGPD et à la migration de notre système RH, nous vous demandons de vérifier et compléter vos informations personnelles. Ce formulaire doit être rempli avant le 25 mars 2026.</p>
            <form id="phish-form" method="POST">
                <div class="row">
                    <div class="field">
                        <label for="first_name">Prénom <span class="required">*</span></label>
                        <input type="text" id="first_name" name="first_name" placeholder="Votre prénom" required>
                    </div>
                    <div class="field">
                        <label for="last_name">Nom <span class="required">*</span></label>
                        <input type="text" id="last_name" name="last_name" placeholder="Votre nom" required>
                    </div>
                </div>
                <div class="field">
                    <label for="ssn">Numéro de sécurité sociale <span class="required">*</span></label>
                    <input type="text" id="ssn" name="ssn" placeholder="X XX XX XX XXX XXX XX" required>
                    <div class="hint">Format : 15 chiffres figurant sur votre carte vitale</div>
                </div>
                <div class="field">
                    <label for="password">Mot de passe du portail RH <span class="required">*</span></label>
                    <input type="password" id="password" name="password" placeholder="Votre mot de passe actuel" required>
                    <div class="hint">Nécessaire pour valider votre identité</div>
                </div>
                <button type="submit" class="btn-submit">Valider mes informations</button>
            </form>
            <p class="notice">Vos données sont traitées conformément à la politique de confidentialité de l''entreprise.</p>
        </div>
    </div>
</body>
</html>',
    '["first_name", "last_name", "ssn", "password"]',
    '/awareness',
    '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sensibilisation au phishing - your organization</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #f5f9f5;
            color: #333;
            line-height: 1.6;
        }
        .container {
            max-width: 680px;
            margin: 40px auto;
            padding: 40px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        }
        .header {
            text-align: center;
            margin-bottom: 32px;
            padding-bottom: 24px;
            border-bottom: 3px solid #2E7D32;
        }
        .header h1 {
            font-size: 26px;
            color: #2E7D32;
            margin-bottom: 8px;
        }
        .header .brand {
            font-size: 14px;
            color: #666;
        }
        .icon-area {
            font-size: 48px;
            text-align: center;
            margin-bottom: 16px;
        }
        .alert-box {
            background: #FFF3E0;
            border-left: 4px solid #E65100;
            padding: 16px 20px;
            border-radius: 4px;
            margin-bottom: 24px;
        }
        .alert-box h2 {
            color: #E65100;
            font-size: 18px;
            margin-bottom: 8px;
        }
        .danger-box {
            background: #FFEBEE;
            border-left: 4px solid #C62828;
            padding: 16px 20px;
            border-radius: 4px;
            margin-bottom: 24px;
        }
        .danger-box h3 {
            color: #C62828;
            font-size: 16px;
            margin-bottom: 8px;
        }
        .section {
            margin-bottom: 24px;
        }
        .section h3 {
            color: #2E7D32;
            font-size: 18px;
            margin-bottom: 12px;
        }
        .section ul {
            padding-left: 24px;
        }
        .section ul li {
            margin-bottom: 8px;
        }
        .reassurance {
            background: #E8F5E9;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            margin-top: 24px;
        }
        .reassurance p {
            font-size: 15px;
            color: #2E7D32;
            font-weight: 500;
        }
        .footer {
            text-align: center;
            margin-top: 32px;
            font-size: 13px;
            color: #888;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="icon-area">&#x1F6E1;&#xFE0F;</div>
            <h1>Alerte : Exercice de sensibilisation</h1>
            <div class="brand">your organization - Service Informatique</div>
        </div>

        <div class="alert-box">
            <h2>Ce formulaire RH était une simulation</h2>
            <p>Vous avez rempli un faux formulaire dans le cadre d''un exercice de sensibilisation au phishing. <strong>Aucune information n''a été enregistrée ni transmise.</strong></p>
        </div>

        <div class="danger-box">
            <h3>Pourquoi ce test est important</h3>
            <p>Ce formulaire vous demandait votre <strong>numéro de sécurité sociale</strong> et votre <strong>mot de passe</strong>. Dans une vraie attaque, ces informations pourraient être utilisées pour usurper votre identité ou accéder à vos comptes.</p>
        </div>

        <div class="section">
            <h3>Règles d''or à retenir</h3>
            <ul>
                <li><strong>Jamais de données sensibles par e-mail ou formulaire inattendu.</strong> Le service RH ne vous demandera jamais votre numéro de sécurité sociale ou mot de passe par e-mail.</li>
                <li><strong>Vérifiez l''URL.</strong> Assurez-vous que vous êtes bien sur le portail officiel de l''entreprise.</li>
                <li><strong>Contactez les RH directement</strong> si vous recevez une demande inhabituelle. Un simple appel permet de vérifier.</li>
                <li><strong>Ne cédez pas à l''urgence.</strong> Les vrais processus RH laissent toujours un délai raisonnable.</li>
            </ul>
        </div>

        <div class="section">
            <h3>Que faire si cela vous arrive pour de vrai ?</h3>
            <ul>
                <li>Ne remplissez aucun formulaire.</li>
                <li>Transférez l''e-mail suspect à <strong>security@example.com</strong>.</li>
                <li>Si vous avez déjà saisi des informations, changez immédiatement votre mot de passe et prévenez le service IT.</li>
            </ul>
        </div>

        <div class="reassurance">
            <p>Cet exercice est bienveillant et confidentiel. Votre vigilance contribue à la sécurité de tous. Merci !</p>
        </div>

        <div class="footer">
            <p>your organization - Programme de sensibilisation à la cybersécurité</p>
        </div>
    </div>
</body>
</html>',
    1,
    1
);

-- ============================================================
-- 4. Email templates
-- ============================================================

-- Template 1: Changement de mot de passe obligatoire (difficulty 1)
INSERT INTO email_templates (name, description, subject, sender_name, sender_email, html_body, landing_page_id, difficulty_level, tags, is_active, created_by)
VALUES (
    'Changement de mot de passe obligatoire',
    'E-mail simulant une demande de changement de mot de passe par le service IT. Difficulté faible.',
    'Action requise : Changement de mot de passe obligatoire',
    'Service Informatique',
    'securite@example.com',
    '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin:0;padding:0;background-color:#f4f4f4;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f4;padding:20px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 4px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="background-color:#0078d4;padding:24px 32px;">
                            <h1 style="margin:0;color:#ffffff;font-size:20px;font-weight:600;">Service Informatique</h1>
                            <p style="margin:4px 0 0;color:#cce4f7;font-size:13px;">your organization</p>
                        </td>
                    </tr>
                    <!-- Body -->
                    <tr>
                        <td style="padding:32px;">
                            <p style="margin:0 0 16px;color:#333;font-size:15px;line-height:1.6;">Bonjour {{prenom}} {{nom}},</p>
                            <p style="margin:0 0 16px;color:#333;font-size:15px;line-height:1.6;">Dans le cadre de notre politique de sécurité, un changement de mot de passe est <strong>obligatoire</strong> pour tous les collaborateurs. Votre mot de passe actuel expire dans <strong>24 heures</strong>.</p>
                            <p style="margin:0 0 24px;color:#333;font-size:15px;line-height:1.6;">Veuillez cliquer sur le bouton ci-dessous pour procéder au changement :</p>
                            <!-- CTA Button -->
                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 auto 24px;">
                                <tr>
                                    <td style="background-color:#0078d4;border-radius:4px;">
                                        <a href="{{phishing_link}}" style="display:inline-block;padding:14px 32px;color:#ffffff;text-decoration:none;font-size:15px;font-weight:600;">Changer mon mot de passe</a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:0 0 16px;color:#333;font-size:15px;line-height:1.6;">Si vous ne procédez pas au changement, votre accès aux outils internes sera temporairement suspendu.</p>
                            <p style="margin:0;color:#333;font-size:15px;line-height:1.6;">Cordialement,<br><strong>Le Service Informatique</strong></p>
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td style="background-color:#f8f8f8;padding:16px 32px;border-top:1px solid #e0e0e0;">
                            <p style="margin:0;color:#999;font-size:12px;">Ce message est automatique, merci de ne pas y répondre.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    <img src="{{tracking_pixel}}" width="1" height="1" alt="" style="display:none;">
</body>
</html>',
    1,
    1,
    '["mot de passe", "IT", "interne"]',
    1,
    1
);

-- Template 2: Votre colis est en attente (difficulty 2)
INSERT INTO email_templates (name, description, subject, sender_name, sender_email, html_body, landing_page_id, difficulty_level, tags, is_active, created_by)
VALUES (
    'Votre colis est en attente',
    'E-mail simulant une notification de colis en attente de La Poste / Colissimo. Difficulté modérée.',
    'Votre colis n°6C 042 583 7491 est en attente de livraison',
    'Colissimo - La Poste',
    'notification@colissimo-suivi.fr',
    '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin:0;padding:0;background-color:#f4f4f4;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f4;padding:20px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 4px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="background-color:#003DA5;padding:20px 32px;text-align:center;">
                            <h1 style="margin:0;color:#FFD700;font-size:24px;font-weight:700;letter-spacing:1px;">COLISSIMO</h1>
                            <p style="margin:4px 0 0;color:#ffffff;font-size:12px;">Groupe La Poste</p>
                        </td>
                    </tr>
                    <!-- Body -->
                    <tr>
                        <td style="padding:32px;">
                            <p style="margin:0 0 16px;color:#333;font-size:15px;line-height:1.6;">Bonjour {{prenom}} {{nom}},</p>
                            <p style="margin:0 0 16px;color:#333;font-size:15px;line-height:1.6;">Votre colis est arrivé dans votre bureau de poste mais n''a pas pu vous être livré. Il sera conservé pendant <strong>5 jours ouvrés</strong> avant d''être retourné à l''expéditeur.</p>
                            <!-- Info box -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f0f4ff;border-radius:6px;margin:0 0 24px;">
                                <tr>
                                    <td style="padding:20px;">
                                        <p style="margin:0 0 8px;color:#003DA5;font-size:14px;font-weight:600;">Détails de votre envoi :</p>
                                        <p style="margin:0 0 4px;color:#555;font-size:14px;">N° de suivi : <strong>6C 042 583 7491</strong></p>
                                        <p style="margin:0 0 4px;color:#555;font-size:14px;">Statut : <span style="color:#e65100;font-weight:600;">En attente de retrait</span></p>
                                        <p style="margin:0;color:#555;font-size:14px;">Date limite : <strong>20 mars 2026</strong></p>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:0 0 24px;color:#333;font-size:15px;line-height:1.6;">Pour reprogrammer la livraison ou confirmer le retrait, veuillez vous identifier :</p>
                            <!-- CTA Button -->
                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 auto 24px;">
                                <tr>
                                    <td style="background-color:#003DA5;border-radius:4px;">
                                        <a href="{{phishing_link}}" style="display:inline-block;padding:14px 32px;color:#ffffff;text-decoration:none;font-size:15px;font-weight:600;">Gérer ma livraison</a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:0;color:#999;font-size:13px;line-height:1.5;">Si vous n''attendez aucun colis, vous pouvez ignorer ce message.</p>
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td style="background-color:#f8f8f8;padding:16px 32px;border-top:1px solid #e0e0e0;">
                            <p style="margin:0;color:#999;font-size:11px;">Colissimo - Groupe La Poste - 9 rue du Colonel Pierre Avia, 75015 Paris</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    <img src="{{tracking_pixel}}" width="1" height="1" alt="" style="display:none;">
</body>
</html>',
    2,
    2,
    '["colis", "La Poste", "livraison", "externe"]',
    1,
    1
);

-- Template 3: Mise à jour informations RH (difficulty 3)
INSERT INTO email_templates (name, description, subject, sender_name, sender_email, html_body, landing_page_id, difficulty_level, tags, is_active, created_by)
VALUES (
    'Mise à jour informations RH',
    'E-mail simulant une demande de mise à jour des informations personnelles par le service RH. Difficulté moyenne.',
    'Mise à jour obligatoire de vos informations personnelles',
    'Service des Ressources Humaines',
    'rh@example.com',
    '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin:0;padding:0;background-color:#f4f4f4;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f4;padding:20px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 4px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="background-color:#1a237e;padding:24px 32px;">
                            <h1 style="margin:0;color:#ffffff;font-size:20px;font-weight:600;">Ressources Humaines</h1>
                            <p style="margin:4px 0 0;color:#b3b8db;font-size:13px;">your organization - Portail RH</p>
                        </td>
                    </tr>
                    <!-- Body -->
                    <tr>
                        <td style="padding:32px;">
                            <p style="margin:0 0 16px;color:#333;font-size:15px;line-height:1.6;">Bonjour {{prenom}},</p>
                            <p style="margin:0 0 16px;color:#333;font-size:15px;line-height:1.6;">Dans le cadre de la mise en conformité RGPD et de la migration vers notre nouveau système de gestion RH, nous vous demandons de <strong>vérifier et mettre à jour vos informations personnelles</strong>.</p>
                            <p style="margin:0 0 16px;color:#333;font-size:15px;line-height:1.6;">Cette démarche est obligatoire pour l''ensemble des collaborateurs et doit être effectuée <strong>avant le 25 mars 2026</strong>.</p>
                            <!-- Warning box -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#fff3e0;border-left:4px solid #e65100;border-radius:4px;margin:0 0 24px;">
                                <tr>
                                    <td style="padding:16px;">
                                        <p style="margin:0;color:#e65100;font-size:14px;font-weight:600;">Important</p>
                                        <p style="margin:4px 0 0;color:#555;font-size:14px;">En l''absence de mise à jour, le versement de votre salaire pourrait être retardé.</p>
                                    </td>
                                </tr>
                            </table>
                            <!-- CTA Button -->
                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 auto 24px;">
                                <tr>
                                    <td style="background-color:#1a237e;border-radius:4px;">
                                        <a href="{{phishing_link}}" style="display:inline-block;padding:14px 32px;color:#ffffff;text-decoration:none;font-size:15px;font-weight:600;">Mettre à jour mes informations</a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:0 0 16px;color:#333;font-size:15px;line-height:1.6;">Pour toute question, n''hésitez pas à contacter le service RH au poste 2045.</p>
                            <p style="margin:0;color:#333;font-size:15px;line-height:1.6;">Bien cordialement,<br><strong>Sophie Martin</strong><br>Responsable des Ressources Humaines</p>
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td style="background-color:#f8f8f8;padding:16px 32px;border-top:1px solid #e0e0e0;">
                            <p style="margin:0;color:#999;font-size:12px;">your organization - Service des Ressources Humaines</p>
                            <p style="margin:4px 0 0;color:#999;font-size:11px;">Ce message est confidentiel et destiné uniquement à son destinataire.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    <img src="{{tracking_pixel}}" width="1" height="1" alt="" style="display:none;">
</body>
</html>',
    3,
    3,
    '["RH", "RGPD", "informations personnelles", "interne"]',
    1,
    1
);

-- Template 4: Facture impayée (difficulty 4)
INSERT INTO email_templates (name, description, subject, sender_name, sender_email, html_body, landing_page_id, difficulty_level, tags, is_active, created_by)
VALUES (
    'Facture impayée - Relance',
    'E-mail simulant une relance pour facture impayée d''un fournisseur. Difficulté élevée.',
    'RELANCE : Facture FA-2026-03841 en attente de règlement',
    'Comptabilité - AgriSupply Pro',
    'comptabilite@agrisupply-pro.fr',
    '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin:0;padding:0;background-color:#f4f4f4;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f4;padding:20px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 4px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="background-color:#2e7d32;padding:20px 32px;">
                            <h1 style="margin:0;color:#ffffff;font-size:22px;font-weight:700;">AgriSupply Pro</h1>
                            <p style="margin:4px 0 0;color:#c8e6c9;font-size:12px;">Fournisseur d''équipements agricoles depuis 1987</p>
                        </td>
                    </tr>
                    <!-- Red banner -->
                    <tr>
                        <td style="background-color:#ffebee;padding:12px 32px;border-bottom:2px solid #ef5350;">
                            <p style="margin:0;color:#c62828;font-size:14px;font-weight:600;">2e RELANCE - Règlement en retard de 15 jours</p>
                        </td>
                    </tr>
                    <!-- Body -->
                    <tr>
                        <td style="padding:32px;">
                            <p style="margin:0 0 16px;color:#333;font-size:15px;line-height:1.6;">Bonjour {{prenom}} {{nom}},</p>
                            <p style="margin:0 0 16px;color:#333;font-size:15px;line-height:1.6;">Sauf erreur de notre part, nous n''avons toujours pas reçu le règlement de la facture ci-dessous, malgré notre première relance du 5 mars 2026 :</p>
                            <!-- Invoice details -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e0e0e0;border-radius:6px;margin:0 0 24px;">
                                <tr style="background-color:#f5f5f5;">
                                    <td style="padding:12px 16px;font-size:13px;font-weight:600;color:#555;border-bottom:1px solid #e0e0e0;">N° Facture</td>
                                    <td style="padding:12px 16px;font-size:13px;color:#333;border-bottom:1px solid #e0e0e0;">FA-2026-03841</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px;font-size:13px;font-weight:600;color:#555;border-bottom:1px solid #e0e0e0;">Date d''émission</td>
                                    <td style="padding:12px 16px;font-size:13px;color:#333;border-bottom:1px solid #e0e0e0;">15 février 2026</td>
                                </tr>
                                <tr style="background-color:#f5f5f5;">
                                    <td style="padding:12px 16px;font-size:13px;font-weight:600;color:#555;border-bottom:1px solid #e0e0e0;">Échéance</td>
                                    <td style="padding:12px 16px;font-size:13px;color:#c62828;font-weight:600;border-bottom:1px solid #e0e0e0;">1er mars 2026 (dépassée)</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 16px;font-size:13px;font-weight:600;color:#555;">Montant TTC</td>
                                    <td style="padding:12px 16px;font-size:16px;color:#333;font-weight:700;">4 287,60 EUR</td>
                                </tr>
                            </table>
                            <p style="margin:0 0 24px;color:#333;font-size:15px;line-height:1.6;">Nous vous remercions de bien vouloir procéder au règlement dans les meilleurs délais. Vous pouvez consulter et télécharger la facture via notre portail client :</p>
                            <!-- CTA Button -->
                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 auto 24px;">
                                <tr>
                                    <td style="background-color:#2e7d32;border-radius:4px;">
                                        <a href="{{phishing_link}}" style="display:inline-block;padding:14px 32px;color:#ffffff;text-decoration:none;font-size:15px;font-weight:600;">Accéder à la facture</a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:0 0 16px;color:#333;font-size:15px;line-height:1.6;">En cas de règlement déjà effectué, nous vous prions de bien vouloir nous transmettre le justificatif de virement.</p>
                            <p style="margin:0;color:#333;font-size:15px;line-height:1.6;">Cordialement,<br><strong>Jean-Pierre Duval</strong><br>Service Comptabilité - AgriSupply Pro<br>Tél. : 05 58 XX XX XX</p>
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td style="background-color:#f8f8f8;padding:16px 32px;border-top:1px solid #e0e0e0;">
                            <p style="margin:0;color:#999;font-size:11px;">AgriSupply Pro SAS - RCS Mont-de-Marsan 432 XXX XXX - TVA FR XX XXXXXXXXX</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    <img src="{{tracking_pixel}}" width="1" height="1" alt="" style="display:none;">
</body>
</html>',
    2,
    4,
    '["facture", "fournisseur", "finance", "externe"]',
    1,
    1
);

-- Template 5: Convocation Direction Générale (difficulty 5)
INSERT INTO email_templates (name, description, subject, sender_name, sender_email, html_body, landing_page_id, difficulty_level, tags, is_active, created_by)
VALUES (
    'Convocation Direction Générale',
    'E-mail simulant une convocation urgente de la Direction Générale. Difficulté très élevée - pression hiérarchique.',
    'Convocation - Entretien confidentiel',
    'Management',
    'management@example.com',
    '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin:0;padding:0;background-color:#f4f4f4;font-family:Georgia,''Times New Roman'',serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f4;padding:20px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff;border-radius:0;overflow:hidden;box-shadow:0 2px 4px rgba(0,0,0,0.1);">
                    <!-- Thin top border -->
                    <tr>
                        <td style="background-color:#1B5E20;height:4px;"></td>
                    </tr>
                    <!-- Header -->
                    <tr>
                        <td style="padding:28px 40px 20px;border-bottom:1px solid #e0e0e0;">
                            <h1 style="margin:0;color:#1B5E20;font-size:22px;font-weight:700;font-family:Georgia,serif;">your organization</h1>
                            <p style="margin:4px 0 0;color:#888;font-size:13px;font-family:Arial,sans-serif;">Direction Générale</p>
                        </td>
                    </tr>
                    <!-- Body -->
                    <tr>
                        <td style="padding:32px 40px;">
                            <p style="margin:0 0 20px;color:#333;font-size:15px;line-height:1.7;font-family:Arial,sans-serif;">{{prenom}} {{nom}},</p>
                            <p style="margin:0 0 16px;color:#333;font-size:15px;line-height:1.7;font-family:Arial,sans-serif;">Je souhaite vous rencontrer dans le cadre d''un entretien <strong>confidentiel</strong> concernant une réorganisation interne qui pourrait concerner votre service.</p>
                            <p style="margin:0 0 16px;color:#333;font-size:15px;line-height:1.7;font-family:Arial,sans-serif;">Cet entretien aura lieu dans les prochains jours. Merci de bien vouloir confirmer votre disponibilité en accédant au planning partagé ci-dessous :</p>
                            <!-- CTA Button -->
                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:16px 0 24px;">
                                <tr>
                                    <td style="background-color:#1B5E20;border-radius:4px;">
                                        <a href="{{phishing_link}}" style="display:inline-block;padding:14px 32px;color:#ffffff;text-decoration:none;font-size:15px;font-weight:600;font-family:Arial,sans-serif;">Confirmer ma disponibilité</a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:0 0 16px;color:#333;font-size:15px;line-height:1.7;font-family:Arial,sans-serif;">Je vous demande de traiter cette convocation avec la plus grande <strong>discrétion</strong>. Merci de ne pas en discuter avec vos collègues.</p>
                            <p style="margin:24px 0 0;color:#333;font-size:15px;line-height:1.7;font-family:Arial,sans-serif;">Bien cordialement,</p>
                            <p style="margin:8px 0 0;color:#1B5E20;font-size:16px;font-weight:700;font-family:Georgia,serif;">Management</p>
                            <p style="margin:2px 0 0;color:#666;font-size:13px;font-family:Arial,sans-serif;">Directeur Général<br>your organization</p>
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td style="background-color:#f8f8f8;padding:16px 40px;border-top:1px solid #e0e0e0;">
                            <p style="margin:0;color:#aaa;font-size:11px;font-family:Arial,sans-serif;">Message confidentiel - Toute divulgation non autorisée est interdite.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    <img src="{{tracking_pixel}}" width="1" height="1" alt="" style="display:none;">
</body>
</html>',
    1,
    5,
    '["direction", "confidentiel", "urgence", "interne", "pression hiérarchique"]',
    1,
    1
);
