<?php
/*
Plugin Name: Генерация страниц из CSV
Description: Плагин для генерации страниц на основе CSV-файла
Version: 1.0
Author: borovlioff
*/

// Создание таблицы page-structure при активации плагина
register_activation_hook( __FILE__, 'generate_pages_csv_create_table' );

function generate_pages_csv_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'page_structure';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id INT(11) NOT NULL AUTO_INCREMENT,
        csv_id INT(11) NOT NULL,
        real_id INT(11) NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
}

// Добавление страницы генерации страниц по CSV в меню администратора
add_action( 'admin_menu', 'generate_pages_csv_add_page' );

function generate_pages_csv_add_page() {
    add_menu_page(
        'Генерация страниц по CSV',
        'Генерация страниц по CSV',
        'manage_options',
        'generate-pages-csv',
        'generate_pages_csv_render_page',
        'dashicons-media-spreadsheet',
        20
    );
}

function generate_pages_csv_render_page() {
    if ( isset( $_POST['generate_pages_csv_submit'] ) ) {
        // Обработка загрузки CSV-файла
        if ( isset( $_FILES['generate_pages_csv_file'] ) ) {
            $csv_file = $_FILES['generate_pages_csv_file'];
            $csv_file_path = $csv_file['tmp_name'];

            // Очистка таблицы page-structure перед загрузкой нового CSV-файла
            generate_pages_csv_clear_table();

            // Чтение CSV-файла и создание страниц
            generate_pages_csv_from_file( $csv_file_path );

            echo 'Страницы созданы.';
        }
    }
    ?>
    <div class="wrap">
        <h1>Генерация страниц по CSV</h1>
        <form method="post" enctype="multipart/form-data">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="generate_pages_csv_file">Выберите CSV-файл:</label>
                    </th>
                    <td>
                        <input type="file" id="generate_pages_csv_file" name="generate_pages_csv_file" accept=".csv">
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="generate_pages_csv_submit" id="generate_pages_csv_submit" class="button button-primary" value="Создать страницы">
            </p>
        </form>
    </div>
    <?php
}

// Функция очистки таблицы page-structure
function generate_pages_csv_clear_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'page_structure';

    $wpdb->query( "TRUNCATE TABLE $table_name" );
}

// Функция получения real_id из таблицы page-structure по csv_id
function generate_pages_csv_get_real_id( $csv_id ) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'page_structure';

    $query = $wpdb->prepare( "SELECT real_id FROM $table_name WHERE csv_id = %d", $csv_id );
    $real_id = $wpdb->get_var( $query );

    return $real_id;
}


// Функция создания страниц из CSV-файла
function generate_pages_csv_from_file( $file_path ) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'page_structure';

    if ( ( $handle = fopen( $file_path, 'r' ) ) !== false ) {
        // Пропуск первой строки
        fgetcsv( $handle );

        while ( ( $data = fgetcsv( $handle ) ) !== false ) {
            $csv_id = $data[0];
            $slug = $data[1];
            $title = $data[2];
            $description = $data[3];

            $parent_real_id = '';

            if ( ! empty( $data[4] ) ) {
                $parent_csv_id = $data[4];
                $parent_real_id = generate_pages_csv_get_real_id( $parent_csv_id );
            }

            // Создание страницы
            $page_id = wp_insert_post( array(
                'post_type'    => 'page',
                'post_title'   => $title,
                'post_name'    => $slug,
                'post_content' => $description,
                'post_parent'  => $parent_real_id,
                'post_status'  => 'publish'
            ) );

            // Сохранение соответствия csv_id и real_id в таблице page_structure
            $wpdb->insert( $table_name, array(
                'csv_id'   => $csv_id,
                'real_id'  => $page_id
            ) );
        }

        fclose( $handle );
    }
}
