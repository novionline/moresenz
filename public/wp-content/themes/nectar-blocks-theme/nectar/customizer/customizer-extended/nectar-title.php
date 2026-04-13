<?php

if ( class_exists( 'WP_Customize_Section' ) ) {
class NectarBlocks_Customizer_Title extends WP_Customize_Section {
    /**
     * Control type.
     *
     * @since  13.0.8
     * @var string
     */
    public $type = 'nectar-title';

    /**
     * Special categorization for the section.
     *
     * @var string
     */
    public $kind = 'default';

    /**
     * Output
     */
    public function render() {
        $description = $this->description;
        //TODO: replace this
        $class = 'accordion-section nectar-customizer-title';

        if ('option' === $this->kind) {
            $class = 'accordion-section ct-option-title';
        }

        ?>

        <li
            id="accordion-section-<?php echo esc_attr( $this->id ); ?>"
            class="<?php echo esc_attr( $class ); ?>">
            <?php if (! empty($this->title) && strpos($this->title, '</div>') === false || $this->kind === 'divider') { ?>
            <h3><?php echo $this->title; ?></h3>
            <?php } else { ?>
            <?php echo $this->title; ?>
            <?php } ?>

            <?php if ( ! empty( $description ) ) { ?>
                <span class="description"><?php echo esc_html( $description ); ?></span>
            <?php } ?>
        </li>
        <?php
    }
}

}
