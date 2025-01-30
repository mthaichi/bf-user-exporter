<?php
/*
Plugin Name: BF User Exporter
Plugin URI:
Description: ユーザー情報とカスタムフィールドをCSVでエクスポートするプラグイン
Version: 1.0.0
Author:
License: GPL2
*/

// 直接アクセス禁止
if (!defined('ABSPATH')) {
    exit;
}

class BF_User_Exporter {
    private $default_fields = "ID\nuser_login\nuser_email\nfirst_name\nlast_name";

    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'handle_export'));

        // プラグイン有効化時に初期値を設定
        register_activation_hook(__FILE__, array($this, 'activate'));
    }

    public function activate() {
        // 初期値が設定されていない場合のみ設定
        if (get_option('bf_user_export_fields') === false) {
            update_option('bf_user_export_fields', $this->default_fields);
        }
    }

    public function add_admin_menu() {
        add_menu_page(
            'BF ユーザーエクスポート',
            'BF ユーザーエクスポート',
            'manage_options',
            'bf-user-exporter',
            array($this, 'display_admin_page'),
            'dashicons-download'
        );
    }

    public function display_admin_page() {
        // エラーメッセージがあれば表示
        settings_errors('bf_user_exporter');
        ?>
        <div class="wrap">
            <h1>BF ユーザー情報エクスポート</h1>
            <form method="post" action="">
                <?php wp_nonce_field('bf_user_export_nonce', 'bf_user_export_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="export_fields">エクスポートするフィールド</label>
                        </th>
                        <td>
                            <textarea name="export_fields" id="export_fields" rows="10" cols="50" class="large-text"><?php echo esc_textarea($this->get_export_fields()); ?></textarea>
                            <p class="description">エクスポートしたいフィールド名を1行に1つずつ入力してください。<br>
                            標準フィールド: ID, user_login, user_email, first_name, last_name など<br>
                            カスタムフィールドはフィールド名をそのまま入力してください。</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('設定を保存', 'primary', 'save_fields'); ?>
                <?php submit_button('CSVエクスポート', 'secondary', 'export_csv'); ?>
            </form>
        </div>
        <?php
    }

    private function get_export_fields() {
        $fields = get_option('bf_user_export_fields');
        if (empty($fields)) {
            $fields = $this->default_fields;
            update_option('bf_user_export_fields', $fields);
        }
        return $fields;
    }

    public function handle_export() {
        if (isset($_POST['save_fields']) && check_admin_referer('bf_user_export_nonce', 'bf_user_export_nonce')) {
            $fields = sanitize_textarea_field($_POST['export_fields']);
            if (empty($fields)) {
                $fields = $this->default_fields;
            }
            update_option('bf_user_export_fields', $fields);
            add_settings_error('bf_user_exporter', 'settings_updated', '設定を保存しました。', 'updated');
        }

        if (isset($_POST['export_csv']) && check_admin_referer('bf_user_export_nonce', 'bf_user_export_nonce')) {
            $fields = explode("\n", $this->get_export_fields());
            $fields = array_map('trim', $fields);
            $fields = array_filter($fields);

            if (empty($fields)) {
                add_settings_error('bf_user_exporter', 'no_fields', 'エクスポートするフィールドが指定されていません。', 'error');
                return;
            }

            // ユーザー一覧を取得
            $users = get_users(array(
                'number' => -1,
                'fields' => 'all_with_meta'
            ));

            if (empty($users)) {
                add_settings_error('bf_user_exporter', 'no_users', 'エクスポート可能なユーザーが見つかりませんでした。', 'error');
                return;
            }

            $data = array();
            // ヘッダー行を追加
            $data[] = $fields;

            // ユーザーデータを取得
            foreach ($users as $user) {
                $user_data = array();
                foreach ($fields as $field) {
                    $field = trim($field);
                    if (empty($field)) continue;

                    if (in_array($field, array('ID', 'user_login', 'user_email', 'first_name', 'last_name', 'display_name', 'user_registered'))) {
                        $user_data[] = isset($user->$field) ? $user->$field : '';
                    } else {
                        $meta_value = get_user_meta($user->ID, $field, true);
                        $user_data[] = is_array($meta_value) ? json_encode($meta_value, JSON_UNESCAPED_UNICODE) : $meta_value;
                    }
                }
                $data[] = $user_data;
            }

            if (count($data) <= 1) {
                add_settings_error('bf_user_exporter', 'no_data', 'エクスポートするデータがありません。', 'error');
                return;
            }

            // CSVをダウンロード
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=bf-users-export-' . date('Y-m-d') . '.csv');

            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOMを追加（Excel対応）

            foreach ($data as $row) {
                fputcsv($output, $row);
            }

            fclose($output);
            exit;
        }
    }
}

new BF_User_Exporter();
