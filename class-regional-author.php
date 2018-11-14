<?php

/**
 * Class Regional_Author
 */
class Regional_Author {
    /**
     * @var null|Regional_Author
     */
    protected static $instance;

    /**
     * @var string Имя новой роли.
     */
    public static $role = 'regional_author';

    /**
     * Regional_Author constructor.
     */
    public function __construct() {
        if ( ! is_admin() ) {
            return;
        }

        add_filter( 'manage_users_columns', array( $this, 'manage_users_columns' ) );
        add_filter( 'manage_users_custom_column', array( $this, 'manage_users_custom_column' ), 10, 3 );
        add_action( 'admin_footer-users.php', array( $this, 'js_ajax_save_default_category' ) );
        add_action( 'wp_ajax_user_default_category', array( $this, 'ajax_save_user_default_category' ) );

        if ( current_user_can( self::$role ) ) {
            $this->verify_access();
            add_filter( 'pre_option_default_category', array( $this, 'option_default_category' ) );
            add_action( 'admin_menu', array( $this, 'remove_menus_and_metabox' ) );
            add_action( 'pre_get_posts', array( $this, 'change_main_query_in_post_table' ) );
            add_filter( 'views_edit-post', '__return_empty_array' );
        }
    }

    public function verify_access() {
        $cat_id = (int) get_user_meta( get_current_user_id(), 'user_default_category', true );

        if ( ! $cat_id ) {
            $GLOBALS['current_user']->allcaps = array( 'read' => true );
            add_action( 'admin_notices', array( $this, 'notice_not_access' ) );
        }
    }

    public function notice_not_access() {
        ?>
        <div id="message" class="notice notice-error">
            <p>Вы не можете публиковать материалы, потому что Администратор не указал для Вас рубрику публикаций.</p>
            <p>Свяжитесь с Администратором сайта, чтобы он сделал это.</p>
        </div>
        <?php
    }

    /**
     * Добавляет новую колонку "Дефолтная рубрика".
     *
     * @param array $columns
     *
     * @return array
     */
    public function manage_users_columns( $columns ) {
        return array_slice( $columns, 0, 2 ) + array( 'default_category' => 'Дефолтная рубрика' ) + $columns;
    }

    /**
     * Возвращает ID дефолтной рубрики.
     *
     * @param $pre_option
     *
     * @return bool|int
     */
    public function option_default_category( $pre_option ) {
        $cat_id = (int) get_user_meta( get_current_user_id(), 'user_default_category', true );

        return $cat_id ? $cat_id : $pre_option;
    }

    /**
     * Удаляет ненужные пункты меню и метабоксы в админке.
     */
    public function remove_menus_and_metabox() {
        remove_menu_page( 'tools.php' );

        remove_meta_box( 'categorydiv', 'post', 'normal' );
        remove_meta_box( 'tagsdiv-post_tag', 'post', 'normal' );
    }

    /**
     * Инициализирует класс. Синглтон.
     *
     * @return Regional_Author
     */
    public static function init() {
        is_null( self::$instance ) AND self::$instance = new self;

        return self::$instance;
    }

    /**
     * Запукается при активации плагина. Добавляет роль.
     */
    public static function activation() {
        if ( ! current_user_can( 'activate_plugins' ) ) {
            die();
        }

        $plugin = isset( $_REQUEST['plugin'] ) ? $_REQUEST['plugin'] : '';
        check_admin_referer( "activate-plugin_{$plugin}" );

        add_role( self::$role, 'Региональный автор', get_role( 'author' )->capabilities );
    }

    /**
     * Запукается при деактивации плагина. Удаляет роль.
     */
    public static function deactivation() {
        if ( ! current_user_can( 'activate_plugins' ) ) {
            die();
        }

        $plugin = isset( $_REQUEST['plugin'] ) ? $_REQUEST['plugin'] : '';
        check_admin_referer( "deactivate-plugin_{$plugin}" );

        remove_role( self::$role );
    }

    /**
     * Изменяет основной запрос в админке.
     *
     * @param WP_Query $query
     */
    public function change_main_query_in_post_table( $query ) {
        if ( $query->is_main_query() && get_current_screen()->id === 'edit-post' ) {
            $query->set( 'author', get_current_user_id() );
        }
    }

    /**
     * Выводит на экран контент ячейки "Дефолтная рубрика".
     *
     * @param string $output
     * @param string $column_name
     * @param int    $user_id
     *
     * @return false|string
     */
    public function manage_users_custom_column( $output, $column_name, $user_id ) {
        if ( 'default_category' === $column_name && user_can( $user_id, self::$role ) ) {
            ob_start();
            ?>
            <div class="user-default-category">
                <input type="hidden" name="user-id" value="<?php echo esc_attr( $user_id ); ?>">
                <?php wp_dropdown_categories( array(
                    'selected'         => (int) get_user_meta( $user_id, 'user_default_category', true ),
                    'show_option_none' => '- Не выбрано -',
                    'hide_empty'       => 0,
                ) ); ?>
            </div>
            <?php
            $output = ob_get_clean();
        }

        return $output;
    }

    /**
     * Выводит на экран JS плагина.
     */
    public function js_ajax_save_default_category() {
        ?>
        <script>
            jQuery(document).ready(function ($) {

                $('.user-default-category select').change(function () {
                    // Запрос
                    var $caAjax = $.post(ajaxurl, {
                        user_id: $(this).prev().val(),
                        cat_id: $(this).val(),
                        action: 'user_default_category'
                    });

                    // Успех
                    $caAjax.success(function (response) {
                        alert(response);
                    });

                    // Ошибка
                    $caAjax.error(function (response) {
                        alert(response.responseText);
                        console.log(response);
                    });
                });
            });
        </script>
        <?php
    }

    /**
     * Обрабатывает AJAX запрос на сохранение или удаление ID дефолтной рубрики для указанного Пользователя.
     */
    function ajax_save_user_default_category() {
        $user_id = empty( $_POST['user_id'] ) ? 0 : (int) $_POST['user_id'];
        $cat_id  = empty( $_POST['cat_id'] ) ? 0 : (int) $_POST['cat_id'];

        if ( ! current_user_can( 'create_users' ) ) {
            wp_die( 'У Вас нет прав.', 403 );
        }

        if ( ! ( $user_id || $cat_id ) ) {
            wp_die( 'Не все параметры переданы.', 400 );
        }

        if ( ! user_can( $user_id, self::$role ) ) {
            wp_die( 'Выбранный пользователь не имеет надлежащей роли.', 400 );
        }

        if ( - 1 === $cat_id ) {
            $status = delete_user_meta( $user_id, 'user_default_category' );
            $text   = $status ? 'Рубрика удалена.' : 'Ошибка. Рубрика не удалена.';
        } else {
            $status = update_user_meta( $user_id, 'user_default_category', $cat_id );
            $text   = $status ? 'Дефолтная рубрика для пользователя установлена.' : 'Ошибка. Рубрика не установлена.';
        }

        wp_die( $text, $status ? '' : 500 );
    }
}
