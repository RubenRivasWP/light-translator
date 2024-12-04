<?php
/**
 * Plugin Name: Light Translator
 * Plugin URI: https://rubenrivas.es/light-translator
 * Description: Un plugin ligero para traducir cadenas de texto específicas con una carga mínima en el servidor.
 * Version: 1.0
 * Author: Rubén Rivas
 * Author URI: https://rubenrivas.es
 * License: GPLv2 or later
 * Text Domain: light-translator
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit; // Evitar accesos directos
}

// Cargar traducciones del plugin
function lt_load_textdomain() {
    load_plugin_textdomain('light-translator', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('plugins_loaded', 'lt_load_textdomain');

// Crear la tabla al activar el plugin
register_activation_hook(__FILE__, 'lt_create_translations_table');
function lt_create_translations_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'lt_translations';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        original_text TEXT NOT NULL,
        translated_text TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Agregar la página de administración
add_action('admin_menu', 'lt_add_admin_page');
function lt_add_admin_page() {
    add_menu_page(
        'Light Translator',
        'Traducciones',
        'manage_options',
        'light-translator',
        'lt_render_admin_page',
        'dashicons-translation',
        20
    );
}

// Renderizar la página de administración
function lt_render_admin_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'lt_translations';

    // Gestión de eliminación
    if (isset($_GET['delete_id'])) {
        $delete_id = intval($_GET['delete_id']);
        $wpdb->delete($table_name, ['id' => $delete_id]);
        echo '<div class="notice notice-success"><p>Traducción eliminada con éxito.</p></div>';
    }

    // Gestión de edición o adición
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['lt_save_translation'])) {
        $original = sanitize_text_field($_POST['original_text']);
        $translation = sanitize_text_field($_POST['translated_text']);
        $edit_id = isset($_POST['edit_id']) ? intval($_POST['edit_id']) : null;

        if ($edit_id) {
            $wpdb->update(
                $table_name,
                ['original_text' => $original, 'translated_text' => $translation],
                ['id' => $edit_id]
            );
            echo '<div class="notice notice-success"><p>Traducción actualizada con éxito.</p></div>';
        } else {
            if (!empty($original) && !empty($translation)) {
                $wpdb->insert($table_name, [
                    'original_text' => $original,
                    'translated_text' => $translation
                ]);
                echo '<div class="notice notice-success"><p>Traducción guardada con éxito.</p></div>';
            }
        }
    }

    // Listar traducciones
    $translations = $wpdb->get_results("SELECT * FROM $table_name");

    // Vista de administración
    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('Light Translator - Traducciones', 'light-translator') . '</h1>';
    echo '<p>' . esc_html__('Agrega, edita o elimina traducciones. Las traducciones se aplican globalmente en el sitio.', 'light-translator') . '</p>';
    
    // Formulario
    echo '<form method="POST">';
    if (isset($_GET['edit_id'])) {
        $edit_id = intval($_GET['edit_id']);
        $edit_translation = $wpdb->get_row("SELECT * FROM $table_name WHERE id = $edit_id");
        echo '<input type="hidden" name="edit_id" value="' . esc_attr($edit_translation->id) . '">';
    }
    echo '<input type="text" name="original_text" placeholder="' . esc_attr__('Texto original', 'light-translator') . '" value="' . esc_attr($edit_translation->original_text ?? '') . '" required>';
    echo '<input type="text" name="translated_text" placeholder="' . esc_attr__('Traducción', 'light-translator') . '" value="' . esc_attr($edit_translation->translated_text ?? '') . '" required>';
    echo '<button type="submit" name="lt_save_translation">' . (isset($edit_translation) ? esc_html__('Actualizar', 'light-translator') : esc_html__('Guardar', 'light-translator')) . '</button>';
    echo '</form>';

    // Listado de traducciones
    echo '<h2>' . esc_html__('Traducciones actuales', 'light-translator') . '</h2>';
    echo '<table>';
    echo '<tr><th>' . esc_html__('Texto original', 'light-translator') . '</th><th>' . esc_html__('Traducción', 'light-translator') . '</th><th>' . esc_html__('Acciones', 'light-translator') . '</th></tr>';
    foreach ($translations as $translation) {
        $edit_url = admin_url("admin.php?page=light-translator&edit_id={$translation->id}");
        $delete_url = admin_url("admin.php?page=light-translator&delete_id={$translation->id}");
        echo "<tr>
                <td>{$translation->original_text}</td>
                <td>{$translation->translated_text}</td>
                <td>
                    <a href='$edit_url' class='button'>" . esc_html__('Editar', 'light-translator') . "</a>
                    <a href='$delete_url' class='button' onclick='return confirm(\"" . esc_html__('¿Estás seguro de eliminar esta traducción?', 'light-translator') . "\")'>" . esc_html__('Eliminar', 'light-translator') . "</a>
                </td>
              </tr>";
    }
    echo '</table>';
    echo '</div>';
}

// Reemplazar texto en el contenido
add_filter('the_content', 'lt_replace_translations');
function lt_replace_translations($content) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'lt_translations';

    $translations = wp_cache_get('lt_translations');
    if (!$translations) {
        $translations = $wpdb->get_results("SELECT original_text, translated_text FROM $table_name");
        wp_cache_set('lt_translations', $translations);
    }

    foreach ($translations as $translation) {
        $content = str_replace($translation->original_text, $translation->translated_text, $content);
    }

    return $content;
}

// Encolar el CSS del plugin
add_action('admin_enqueue_scripts', 'lt_enqueue_admin_styles');
function lt_enqueue_admin_styles() {
    wp_enqueue_style('light-translator-styles', plugin_dir_url(__FILE__) . 'assets/light-translator.css', [], filemtime(plugin_dir_path(__FILE__) . 'assets/light-translator.css'));
}
