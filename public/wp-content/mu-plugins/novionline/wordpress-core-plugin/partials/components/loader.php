<?php

use NoviOnline\Core;

/**
 * @var int $size
 * @var string $theme 'dark' | 'light
 * @var bool $withText
 * @var string $loaderText
 */

//set default values
if (!isset($size)) $size = 40;
if (!isset($theme)) $theme = 'dark';
if (!isset($withText)) $withText = true;
if (!isset($loaderText)) $loaderText = __("Loading..", Core::TEXT_DOMAIN);

$containerClasses = ['novi-loader', 'novi-loader--theme-' . trim($theme)];

?>

<div class="<?php echo implode(' ', $containerClasses); ?>" role="presentation">

    <svg class="novi-loader__spinner"
         width="<?php echo $size ?>px"
         height="<?php echo $size ?>px"
         viewBox="0 0 66 66"
         xmlns="http://www.w3.org/2000/svg">
        <circle class="novi-loader__spinner-circle"
                fill="none"
                stroke-width="6"
                stroke-linecap="round"
                cx="33"
                cy="33"
                r="30"/>
    </svg>

    <?php if ($withText && $loaderText): ?>
        <small class="novi-loader__text">
            <?php echo $loaderText; ?>
        </small>
    <?php endif; ?>

</div>