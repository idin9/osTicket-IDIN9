<?php
header("Content-Type: text/html; charset=UTF-8");
header("Content-Security-Policy: frame-ancestors ".$cfg->getAllowIframes()."; script-src 'self' 'unsafe-inline' 'unsafe-eval'; object-src 'none'");

$title = ($ost && ($title=$ost->getPageTitle()))
    ? $title : ('osTicket :: '.__('Staff Control Panel'));

// Theme (dark/light/system): URL param > cookie > default (follow system)
$theme = 'system';
if (isset($_GET['theme']) && in_array($_GET['theme'], array('dark', 'light', 'system'))) {
    $theme = $_GET['theme'];
    setcookie('ost_theme', $theme, time() + 86400 * 365, '/');
} elseif (isset($_COOKIE['ost_theme'])
        && in_array($_COOKIE['ost_theme'], array('dark', 'light', 'system'))) {
    $theme = $_COOKIE['ost_theme'];
}

if (!isset($_SERVER['HTTP_X_PJAX'])) { ?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html<?php
if (($lang = Internationalization::getCurrentLanguage())
        && ($info = Internationalization::getLanguageInfo($lang))
        && (@$info['direction'] == 'rtl'))
    echo ' dir="rtl" class="rtl"';
if ($lang) {
    echo ' lang="' . Internationalization::rfc1766($lang) . '"';
}
if ($theme != 'system')
    echo ' data-theme="' . $theme . '"';

// Dropped IE Support Warning
if (osTicket::is_ie())
    $ost->setWarning(__('osTicket no longer supports Internet Explorer.'));
?>>
<head>
    <meta http-equiv="content-type" content="text/html; charset=UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <meta http-equiv="cache-control" content="no-cache" />
    <meta http-equiv="pragma" content="no-cache" />
    <meta http-equiv="x-pjax-version" content="<?php echo GIT_VERSION; ?>">
    <title><?php echo Format::htmlchars($title); ?></title>
    <!--[if IE]>
    <style type="text/css">
        .tip_shadow { display:block !important; }
    </style>
    <![endif]-->
    <script type="text/javascript" src="<?php echo ROOT_PATH; ?>js/jquery-3.7.0.min.js"></script>
    <link rel="stylesheet" href="<?php echo ROOT_PATH ?>css/thread.css" media="all">
    <link rel="stylesheet" href="<?php echo ROOT_PATH ?>scp/css/scp.css" media="all">
    <link rel="stylesheet" href="<?php echo ROOT_PATH; ?>css/redactor.css" media="screen">
    <link rel="stylesheet" href="<?php echo ROOT_PATH ?>css/typeahead.css" media="screen">
    <link type="text/css" href="<?php echo ROOT_PATH; ?>css/ui-lightness/jquery-ui-1.13.2.custom.min.css"
         rel="stylesheet" media="screen" />
    <link rel="stylesheet" href="<?php echo ROOT_PATH ?>css/jquery-ui-timepicker-addon.css" media="all">
    <link type="text/css" rel="stylesheet" href="<?php echo ROOT_PATH; ?>css/font-awesome.min.css">
    <!--[if IE 7]>
    <link rel="stylesheet" href="<?php echo ROOT_PATH; ?>css/font-awesome-ie7.min.css">
    <![endif]-->
    <link type="text/css" rel="stylesheet" href="<?php echo ROOT_PATH ?>scp/css/dropdown.css">
    <link type="text/css" rel="stylesheet" href="<?php echo ROOT_PATH; ?>css/loadingbar.css"/>
    <link type="text/css" rel="stylesheet" href="<?php echo ROOT_PATH; ?>css/flags.css">
    <link type="text/css" rel="stylesheet" href="<?php echo ROOT_PATH; ?>css/select2.min.css">
    <link type="text/css" rel="stylesheet" href="<?php echo ROOT_PATH; ?>css/rtl.css"/>
    <link type="text/css" rel="stylesheet" href="<?php echo ROOT_PATH ?>scp/css/translatable.css"/>
    <?php
    // Modern UI switcher — respects: URL param > staff preference > system config
    $useModernUI = $cfg->isModernUiEnabled();
    if (isset($_GET['ui'])) {
        if ($_GET['ui'] == 'modern') {
            $useModernUI = true;
            setcookie('ost_modern_ui', '1', time() + 86400 * 365, '/');
        } elseif ($_GET['ui'] == 'classic') {
            $useModernUI = false;
            setcookie('ost_modern_ui', '0', time() + 86400 * 365, '/');
        }
    } elseif (isset($_COOKIE['ost_modern_ui'])) {
        $useModernUI = $_COOKIE['ost_modern_ui'] == '1';
    } elseif (isset($thisstaff) && $thisstaff->modern_ui) {
        if ($thisstaff->modern_ui == 'modern')
            $useModernUI = true;
        elseif ($thisstaff->modern_ui == 'classic')
            $useModernUI = false;
    }
    if ($useModernUI) { ?>
    <link rel="stylesheet" href="<?php echo ROOT_PATH ?>css/tokens.css" media="all">
    <link rel="stylesheet" href="<?php echo ROOT_PATH ?>scp/css/modern/scp.css" media="all">
    <link rel="stylesheet" href="<?php echo ROOT_PATH ?>scp/css/modern/dashboard.css" media="all">
    <?php } ?>
    <!-- Favicons -->
    <link rel="icon" type="image/png" href="<?php echo ROOT_PATH ?>images/oscar-favicon-32x32.png" sizes="32x32" />
    <link rel="icon" type="image/png" href="<?php echo ROOT_PATH ?>images/oscar-favicon-16x16.png" sizes="16x16" />

    <?php
    if($ost && ($headers=$ost->getExtraHeaders())) {
        echo "\n\t".implode("\n\t", $headers)."\n";
    }
    ?>
</head>
<body>
<nav id="skip-link"><a href="#pjax-container"><?php echo __('Skip to content'); ?></a></nav>
<div id="container">
    <?php
    if($ost->getError())
        echo sprintf('<div id="error_bar">%s</div>', $ost->getError());
    elseif($ost->getWarning())
        echo sprintf('<div id="warning_bar">%s</div>', $ost->getWarning());
    elseif($ost->getNotice())
        echo sprintf('<div id="notice_bar">%s</div>', $ost->getNotice());
    ?>
    <header id="header">
        <a href="<?php echo ROOT_PATH ?>scp/index.php" class="no-pjax" id="logo">
            <span class="valign-helper"></span>
            <img src="<?php echo ROOT_PATH ?>scp/logo.php?<?php echo strtotime($cfg->lastModified('staff_logo_id')); ?>" alt="osTicket &mdash; <?php echo __('Customer Support System'); ?>"/>
        </a>
        <?php if ($useModernUI) { ?>
        <div id="theme-switcher" class="no-pjax" role="group" aria-label="<?php echo __('Color theme'); ?>">
            <button type="button" class="theme-btn<?php echo $theme == 'light' ? ' active' : ''; ?>"
                data-theme-target="light" aria-pressed="<?php echo $theme == 'light' ? 'true' : 'false'; ?>"
                title="<?php echo __('Light'); ?>" aria-label="<?php echo __('Light'); ?>"><i class="icon-sun"></i></button>
            <button type="button" class="theme-btn<?php echo $theme == 'dark' ? ' active' : ''; ?>"
                data-theme-target="dark" aria-pressed="<?php echo $theme == 'dark' ? 'true' : 'false'; ?>"
                title="<?php echo __('Dark'); ?>" aria-label="<?php echo __('Dark'); ?>"><i class="icon-moon"></i></button>
            <button type="button" class="theme-btn<?php echo $theme == 'system' ? ' active' : ''; ?>"
                data-theme-target="system" aria-pressed="<?php echo $theme == 'system' ? 'true' : 'false'; ?>"
                title="<?php echo __('Follow system'); ?>" aria-label="<?php echo __('Follow system'); ?>"><i class="icon-adjust"></i></button>
        </div>
        <script type="text/javascript">
        (function() {
            var switcher = document.getElementById('theme-switcher');
            if (!switcher) return;
            switcher.addEventListener('click', function(e) {
                var btn = e.target && e.target.closest ? e.target.closest('[data-theme-target]') : null;
                if (!btn) return;
                var t = btn.getAttribute('data-theme-target');
                var root = document.documentElement;
                if (t === 'system')
                    root.removeAttribute('data-theme');
                else
                    root.setAttribute('data-theme', t);
                document.cookie = 'ost_theme=' + t + '; path=/; max-age=31536000';
                switcher.querySelectorAll('[data-theme-target]').forEach(function(b) {
                    var on = b === btn;
                    b.setAttribute('aria-pressed', on ? 'true' : 'false');
                    b.classList.toggle('active', on);
                });
            });
        })();
        </script>
        <?php } ?>
        <p id="info" class="pull-right no-pjax"><?php echo sprintf(__('Welcome, %s.'), '<strong>'.$thisstaff->getFirstName().'</strong>'); ?>
           <?php
            if($thisstaff->isAdmin() && !defined('ADMINPAGE')) { ?>
            | <a href="<?php echo ROOT_PATH ?>scp/admin.php" class="no-pjax"><?php echo __('Admin Panel'); ?></a>
            <?php }else{ ?>
            | <a href="<?php echo ROOT_PATH ?>scp/index.php" class="no-pjax"><?php echo __('Agent Panel'); ?></a>
            <?php } ?>
            | <a href="<?php echo ROOT_PATH ?>scp/profile.php"><?php echo __('Profile'); ?></a>
            | <a href="<?php echo ROOT_PATH ?>scp/logout.php?auth=<?php echo $ost->getLinkToken(); ?>" class="no-pjax"><?php echo __('Log Out'); ?></a>
        </p>
    </header>
    <button class="nav-toggle" aria-label="<?php echo __('Toggle navigation'); ?>" aria-expanded="false">☰</button>
    <div id="pjax-container" class="<?php if ($_POST) echo 'no-pjax'; ?>">
<?php } else {
    header('X-PJAX-Version: ' . GIT_VERSION);
    if ($pjax = $ost->getExtraPjax()) { ?>
    <script type="text/javascript">
    <?php foreach (array_filter($pjax) as $s) echo $s.";"; ?>
    </script>
    <?php }
    foreach ($ost->getExtraHeaders() as $h) {
        if (strpos($h, '<script ') !== false)
            echo $h;
    } ?>
    <title><?php echo ($ost && ($title=$ost->getPageTitle()))?$title:'osTicket :: '.__('Staff Control Panel'); ?></title><?php
} # endif X_PJAX ?>
    <nav aria-label="<?php echo __('Main navigation'); ?>">
    <ul id="nav">
<?php include STAFFINC_DIR . "templates/navigation.tmpl.php"; ?>
    </ul>
    </nav>
    <?php include STAFFINC_DIR . "templates/sub-navigation.tmpl.php"; ?>

        <main id="content">
        <?php if(isset($errors['err'])) { ?>
            <div id="msg_error" role="alert"><?php echo $errors['err']; ?></div>
        <?php }elseif($msg) { ?>
            <div id="msg_notice" role="status"><?php echo $msg; ?></div>
        <?php }elseif($warn) { ?>
            <div id="msg_warning" role="alert"><?php echo $warn; ?></div>
        <?php }
        foreach (Messages::getMessages() as $M) { ?>
            <div class="<?php echo strtolower($M->getLevel()); ?>-banner"><?php
                echo (string) $M; ?></div>
<?php   } ?>
