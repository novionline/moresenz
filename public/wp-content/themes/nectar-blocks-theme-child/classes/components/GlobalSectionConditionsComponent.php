<?php

namespace NoviOnline;

use NoviOnline\Core\Singleton;

/**
 * Class GlobalSectionConditionsComponent
 * 
 * Handles custom display logic for Global Sections
 * 
 * @package NoviOnline
 */
class GlobalSectionConditionsComponent extends Singleton {

    private array $footerHooks = [
        'nectar_hook_global_section_footer',
        'nectar_hook_global_section_parallax_footer',
        'nectar_hook_global_section_after_footer'
    ];

    /**
     * GlobalSectionConditionsComponent constructor.
     */
    protected function __construct() {
        
        //remove footer global sections on 404 pages by unhooking them early
        add_action('wp', [$this, 'handleFooterOn404'], 15);
    }

    /**
     * Remove footer global sections on 404 pages
     * This runs after global sections are registered (priority 15, after the default wp priority)
     */
    public function handleFooterOn404(): void {
        
        //only run on 404 pages
        if (!is_404()) {
            return;
        }
        
        //remove all actions from footer hooks to prevent footer global sections from showing
        foreach ($this->footerHooks as $hook) {
            remove_all_actions($hook);
        }
    }
}

