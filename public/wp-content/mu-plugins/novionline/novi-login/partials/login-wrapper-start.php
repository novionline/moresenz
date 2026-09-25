<?php

/**
 * @var string $heading
 * @var string $intro
 * @var string $supportTitle
 * @var string $supportText
 * @var string $supportName
 * @var string $supportRole
 * @var string $supportMailHref
 * @var string $supportTelHref
 * @var string $supportImageUrl
 * @var string $supportImageAlt
 * @var string $supportMailLabel
 * @var string $supportTelLabel
 */

if (!isset($heading)) $heading = '';
if (!isset($intro)) $intro = '';
if (!isset($supportTitle)) $supportTitle = '';
if (!isset($supportText)) $supportText = '';
if (!isset($supportName)) $supportName = '';
if (!isset($supportRole)) $supportRole = '';
if (!isset($supportMailHref)) $supportMailHref = '';
if (!isset($supportTelHref)) $supportTelHref = '';
if (!isset($supportImageUrl)) $supportImageUrl = '';
if (!isset($supportImageAlt)) $supportImageAlt = '';
if (!isset($supportMailLabel)) $supportMailLabel = 'Mail ons';
if (!isset($supportTelLabel)) $supportTelLabel = 'Bel ons';

?>
<div class="novi-login">
    <section class="novi-login__panel novi-login__panel--left">
        <div class="novi-login__background">
            <svg class="novi-login__background-art" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 1680 1056" aria-hidden="true" focusable="false">
                <mask id="novi-login-mask" width="1680" height="1056" x="0" y="0" maskUnits="userSpaceOnUse" style="mask-type:alpha">
                    <path fill="#000" d="M0 0h1680v1056H0z"></path>
                </mask>
                <g mask="url(#novi-login-mask)">
                    <path stroke="url(#novi-login-gradient)" stroke-linejoin="round" stroke-width="1.5" d="M328-417h410.87L2218 1062.13V1473M328-417V-6.13L1807.13 1473H2218M328-417l1890 1890"></path>
                </g>
                <defs>
                    <linearGradient id="novi-login-gradient" x1="959" x2="1682" y1="216" y2="939" gradientUnits="userSpaceOnUse">
                        <stop stop-color="#ffffff" stop-opacity="0"></stop>
                        <stop offset="1" stop-color="#ffffff"></stop>
                    </linearGradient>
                </defs>
            </svg>
        </div>
        <div class="novi-login__panel-inner">
            <figure class="novi-login__brand">
                <svg class="novi-login__brand-image" xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 48 48" aria-hidden="true" focusable="false">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M0.662983 0H10.8095C10.9853 0 11.154 0.0698499 11.2783 0.194183L42.2637 31.1796L46.3994 27.044L36.7217 17.3662C36.5974 17.2419 36.5275 17.0733 36.5275 16.8974V0.662983C36.5275 0.296828 36.8243 0 37.1905 0H47.337C47.7032 0 48 0.296828 48 0.662983V47.337C48 47.7032 47.7032 48 47.337 48H37.1905C37.0147 48 36.846 47.9301 36.7217 47.8058L5.73625 16.8204L1.60058 20.956L11.2783 30.6338C11.4026 30.7581 11.4725 30.9267 11.4725 31.1026V47.337C11.4725 47.7032 11.1757 48 10.8095 48H0.662983C0.296828 48 0 47.7032 0 47.337V0.662983C0 0.296828 0.296828 0 0.662983 0ZM1.32597 2.26357V10.5349L5.73625 14.9452L9.87191 10.8095L1.32597 2.26357ZM10.8095 11.7471L6.67385 15.8828L36.5275 45.7364V37.4651L10.8095 11.7471ZM37.1905 36.2529L11.4725 10.5349L11.4725 2.26357L41.3262 32.1172L37.1905 36.2529ZM37.8535 38.7911V46.674H45.7364L37.8535 38.7911ZM46.674 45.7364V37.4651L42.2637 33.0548L38.1281 37.1905L46.674 45.7364ZM43.2013 32.1172L46.674 35.5899V28.6445L43.2013 32.1172ZM46.674 25.4434L37.8535 16.6228V1.32597H46.674V25.4434ZM10.1465 1.32597L10.1465 9.20893L2.26357 1.32597H10.1465ZM4.79865 15.8828L1.32597 12.4101V19.3555L4.79865 15.8828ZM1.32597 22.5566V46.674H10.1465V31.3772L1.32597 22.5566Z" fill="#945EF0"/>
                </svg>
            </figure>
            <div class="novi-login__content">
                <?php if ($heading): ?>
                    <h1 class="novi-login__heading"><?php echo esc_html($heading); ?></h1>
                <?php endif; ?>
                <?php if ($intro): ?>
                    <p class="novi-login__intro"><?php echo esc_html($intro); ?></p>
                <?php endif; ?>
                <blockquote class="novi-login__support">
                    <div class="novi-login__support-card">
                        <?php if ($supportTitle): ?>
                            <h5 class="novi-login__support-title"><?php echo esc_html($supportTitle); ?></h5>
                        <?php endif; ?>
                        <?php if ($supportText): ?>
                            <p class="novi-login__support-text"><?php echo esc_html($supportText); ?></p>
                        <?php endif; ?>
                        <div class="novi-login__support-body">
                            <?php if ($supportImageUrl): ?>
                                <figure class="novi-login__support-avatar">
                                    <img
                                        src="<?php echo esc_url($supportImageUrl); ?>"
                                        alt="<?php echo esc_attr($supportImageAlt); ?>"
                                        loading="lazy"
                                        width="90"
                                        height="90"
                                        class="novi-login__support-image"
                                    >
                                </figure>
                            <?php endif; ?>
                            <div class="novi-login__support-meta">
                                <div class="novi-login__support-person">
                                    <?php if ($supportName): ?>
                                        <strong class="novi-login__support-name"><?php echo esc_html($supportName); ?></strong>
                                    <?php endif; ?>
                                    <?php if ($supportRole): ?>
                                        <p class="novi-login__support-role"><?php echo esc_html($supportRole); ?></p>
                                    <?php endif; ?>
                                    <?php if ($supportWebsite): ?>
                                        <a target="_blank" href="<?php echo esc_url($supportWebsite); ?>" class="novi-login__support-website"><?php echo esc_html($supportWebsiteLabel); ?></a>
                                    <?php endif; ?>
                                </div>
                                <div class="novi-login__support-actions">
                                    <?php if ($supportMailHref): ?>
                                        <a href="<?php echo esc_url($supportMailHref); ?>" class="novi-login__button novi-login__button--primary">
                                            <span class="novi-login__button-text"><?php echo esc_html($supportMailLabel); ?></span>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($supportTelHref): ?>
                                        <a href="<?php echo esc_url($supportTelHref); ?>" class="novi-login__button novi-login__button--secondary">
                                            <span class="novi-login__button-text"><?php echo esc_html($supportTelLabel); ?></span>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </blockquote>
            </div>
        </div>
    </section>
    <section class="novi-login__panel novi-login__panel--right">
        <div class="novi-login__form-inner">
