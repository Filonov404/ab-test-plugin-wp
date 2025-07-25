<?php
/*
Plugin Name: Simple A/B Test Block
Description: Implements a simple A/B test for a block of content with random assignment, metric collection and a mini-report in the WordPress admin area.
Version: 1.0.1
Author: Aleksandr Filonov
License: GPLv2 or later
*/

if (! defined('ABSPATH')) {
    exit;
}

class Simple_AB_Test_Block
{
    private $option_name = 'simple_ab_test_data';
    private $cookie_name = 'simple_ab_test_variant';
    private $variants    = array('A', 'B');

    public function __construct()
    {
        add_action('init', array($this, 'maybe_set_variant_cookie'));
        add_shortcode('ab_test_block', array($this, 'render_block'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_ab_test_click', array($this, 'handle_click'));
        add_action('wp_ajax_nopriv_ab_test_click', array($this, 'handle_click'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
    }

    public function maybe_set_variant_cookie()
    {
        $variant = $this->variants[array_rand($this->variants)];
        setcookie($this->cookie_name, $variant, [
            'expires' => time() + MONTH_IN_SECONDS,
            'path' => '/',
            'secure' => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[$this->cookie_name] = $variant;
    }

    public function render_block($atts)
    {
        $variant = isset($_COOKIE[$this->cookie_name]) && in_array($_COOKIE[$this->cookie_name], $this->variants, true)
            ? $_COOKIE[$this->cookie_name]
            : 'A';

        $data = get_option($this->option_name, array());
        if (! isset($data[$variant])) {
            $data[$variant] = array('impressions' => 0, 'clicks' => 0);
        }
        $data[$variant]['impressions']++;
        update_option($this->option_name, $data);

        $nonce       = wp_create_nonce('ab_test_nonce');
        $heading     = ('A' === $variant) ? 'Variant A Heading' : 'Variant B Heading';
        $button_text = ('A' === $variant) ? 'Click me A' : 'Click me B';

        ob_start();
?>
        <div class="ab-test-block variant-<?php echo esc_attr(strtolower($variant)); ?>">
            <h2><?php echo esc_html($heading); ?></h2>
            <button class="ab-test-button" data-variant="<?php echo esc_attr($variant); ?>" data-nonce="<?php echo esc_attr($nonce); ?>">
                <?php echo esc_html($button_text); ?>
            </button>
        </div>
    <?php
        return ob_get_clean();
        error_log('A/B shortcode rendered');
    }

    public function enqueue_scripts()
    {
        wp_register_script('simple-ab-test-script', plugin_dir_url(__FILE__) . 'ab-test.js', array('jquery'), '1.0.0', true);
        wp_enqueue_script('simple-ab-test-script');
        wp_localize_script('simple-ab-test-script', 'abTestData', array(
            'ajax_url' => admin_url('admin-ajax.php'),
        ));
    }

    public function handle_click()
    {
        check_ajax_referer('ab_test_nonce');

        $variant = isset($_POST['variant']) ? sanitize_text_field(wp_unslash($_POST['variant'])) : '';
        if (! in_array($variant, $this->variants, true)) {
            wp_send_json_error('Invalid variant.');
        }

        $data = get_option($this->option_name, array());
        if (! isset($data[$variant])) {
            $data[$variant] = array('impressions' => 0, 'clicks' => 0);
        }
        $data[$variant]['clicks']++;
        update_option($this->option_name, $data);

        wp_send_json_success();
    }

    public function add_admin_menu()
    {
        add_menu_page(
            'A/B Test Report',
            'A/B Test Report',
            'manage_options',
            'simple-ab-test-report',
            array($this, 'render_report'),
            'dashicons-chart-bar'
        );
    }

    public function render_report()
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $data = get_option($this->option_name, array());
        foreach ($this->variants as $variant) {
            if (! isset($data[$variant])) {
                $data[$variant] = array('impressions' => 0, 'clicks' => 0);
            }
        }

        $rates = array();
        foreach ($this->variants as $variant) {
            $impressions       = (int) $data[$variant]['impressions'];
            $clicks            = (int) $data[$variant]['clicks'];
            $rates[$variant] = ($impressions > 0) ? ($clicks / $impressions) : 0;
        }

        $winner_message = '';
        if ($rates['A'] > $rates['B']) {
            $winner_message = 'Variant A is currently performing better.';
        } elseif ($rates['B'] > $rates['A']) {
            $winner_message = 'Variant B is currently performing better.';
        } else {
            $winner_message = 'Both variants are currently performing equally.';
        }
    ?>
        <div class="wrap">
            <h1>A/B Test Report</h1>
            <table class="widefat" style="max-width:600px;">
                <thead>
                    <tr>
                        <th>Variant</th>
                        <th>Impressions</th>
                        <th>Clicks</th>
                        <th>Conversion Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($this->variants as $variant) :
                        $impressions = (int) $data[$variant]['impressions'];
                        $clicks      = (int) $data[$variant]['clicks'];
                        $rate        = $rates[$variant];
                    ?>
                        <tr>
                            <td><?php echo esc_html($variant); ?></td>
                            <td><?php echo esc_html($impressions); ?></td>
                            <td><?php echo esc_html($clicks); ?></td>
                            <td><?php echo esc_html($impressions > 0 ? round($rate * 100, 2) . '%' : '0%'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p style="margin-top:20px;"><strong><?php echo esc_html($winner_message); ?></strong></p>
        </div>
<?php
    }
}

new Simple_AB_Test_Block();
