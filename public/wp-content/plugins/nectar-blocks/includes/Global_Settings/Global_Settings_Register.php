<?php

namespace Nectar\Global_Settings;
use Nectar\Global_Settings\{
  Global_Colors,
  Global_Typography,
  Nectar_Blocks_Options,
  Nectar_Plugin_Options,
  Nectar_Modules,
  Nectar_Custom_Fonts,
  Code_Options
};

/**
 * Global_Settings
 * @version 1.3.0
 * @since 0.0.2
 */
class Global_Settings_Register {
  function __construct() {
    new Nectar_Blocks_Options();
    new Code_Options();
    new Nectar_Plugin_Options();
    new Nectar_Modules();

    // Editor Settings
    new Global_Colors();
    new Global_Typography();
    new Nectar_Custom_Fonts();
  }
}