<?php
/**
 * Package A.1 Integration & Unit Test Suite
 * Tracks and tests:
 * 1. Empty/whitespace FAQ rejection (400) without clearing existing data
 * 2. Unknown solder alloy rejection (400)
 * 3. Partial REST updates leaving omitted fields untouched
 * 4. Verification with outdated/mismatching expected_hash returns 409 Conflict without modifying fields
 * 5. Verification with valid expected_hash succeeds (200 OK)
 * 6. Content modification after verification triggers outdated status
 * 7. Document-wide anchor uniqueness (section vs H2 collisions, template ID collisions)
 * 8. Canonical alloy normalization (wood -> vuda, rose -> roze)
 * 9. Rank Math schema ownership detection
 */

define('ABSPATH', dirname(__DIR__) . '/');

// Mock WordPress environment
$mock_posts = [];
$mock_meta = [];
$mock_options = [];
$mock_current_user_id = 42;
$mock_roles = [
    'administrator' => new class {
        public $caps = ['tchp_review_content' => true, 'edit_posts' => true];
        public function has_cap($cap) { return !empty($this->caps[$cap]); }
        public function add_cap($cap) { $this->caps[$cap] = true; }
    },
    'editor' => new class {
        public $caps = ['tchp_review_content' => true, 'edit_posts' => true];
        public function has_cap($cap) { return !empty($this->caps[$cap]); }
        public function add_cap($cap) { $this->caps[$cap] = true; }
    }
];
$mock_hooks = [
    'init' => [],
    'rest_pre_dispatch' => [],
    'rest_pre_insert_post' => [],
    'rest_api_init' => [],
    'save_post' => [],
    'wp_after_insert_post' => [],
    'rest_after_insert_post' => [],
];
$mock_rest_routes = [];

function add_action($tag, $callback, $priority = 10, $accepted_args = 1) {
    global $mock_hooks;
    $mock_hooks[$tag][] = $callback;
}
function add_filter($tag, $callback, $priority = 10, $accepted_args = 1) {
    global $mock_hooks;
    $mock_hooks[$tag][] = $callback;
}
function get_role($slug) {
    global $mock_roles;
    return $mock_roles[$slug] ?? null;
}
function get_current_user_id() {
    global $mock_current_user_id;
    return $mock_current_user_id;
}
function user_can($user_id, $cap, ...$args) {
    if ($cap === 'tchp_review_content') return $user_id === 42 || $user_id === 1;
    if ($cap === 'edit_post') return true;
    return false;
}
function current_user_can($cap, ...$args) {
    return user_can(get_current_user_id(), $cap, ...$args);
}
function current_time($type) {
    return date('c');
}
function sanitize_key($key) {
    return strtolower(preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)$key));
}
function sanitize_text_field($str) {
    return trim(strip_tags((string)$str));
}
function wp_strip_all_tags($str) {
    return trim(strip_tags((string)$str));
}
function absint($val) {
    return abs((int)$val);
}
function esc_html($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}
function esc_attr($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}
function esc_url_raw($url) {
    return filter_var($url, FILTER_SANITIZE_URL) ?: '';
}
function get_post($post_id) {
    global $mock_posts;
    return $mock_posts[$post_id] ?? null;
}
function get_the_title($post_id) {
    global $mock_posts;
    return $mock_posts[$post_id]->post_title ?? '';
}
function get_post_field($field, $post_id) {
    global $mock_posts;
    return $mock_posts[$post_id]->$field ?? '';
}
function get_post_meta($post_id, $key, $single = false) {
    global $mock_meta;
    if (!isset($mock_meta[$post_id][$key])) {
        return $single ? '' : [];
    }
    return $single ? $mock_meta[$post_id][$key] : [$mock_meta[$post_id][$key]];
}
function update_post_meta($post_id, $key, $value) {
    global $mock_meta;
    $mock_meta[$post_id][$key] = $value;
    return true;
}
function delete_post_meta($post_id, $key) {
    global $mock_meta;
    unset($mock_meta[$post_id][$key]);
    return true;
}
function metadata_exists($type, $post_id, $key) {
    global $mock_meta;
    return isset($mock_meta[$post_id]) && array_key_exists($key, $mock_meta[$post_id]);
}
function wp_is_post_revision($post_id) { return false; }
function wp_is_post_autosave($post_id) { return false; }
function get_option($key, $default = false) {
    global $mock_options;
    return $mock_options[$key] ?? $default;
}
function get_userdata($user_id) {
    if ($user_id === 42) {
        $u = new stdClass();
        $u->display_name = 'Инженер ОТК Иванов';
        $u->user_login = 'ivanov';
        return $u;
    }
    return null;
}
function sanitize_title($str) {
    $translit = [
        'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'e','ж'=>'zh','з'=>'z',
        'и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r',
        'с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'kh','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'shch',
        'ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya',
        'А'=>'a','Б'=>'b','В'=>'v','Г'=>'g','Д'=>'d','Е'=>'e','Ё'=>'e','Ж'=>'zh','З'=>'z',
        'И'=>'i','Й'=>'y','К'=>'k','Л'=>'l','М'=>'m','Н'=>'n','О'=>'o','П'=>'p','Р'=>'r',
        'С'=>'s','Т'=>'t','У'=>'u','Ф'=>'f','Х'=>'kh','Ц'=>'ts','Ч'=>'ch','Ш'=>'sh','Щ'=>'shch',
        'Ъ'=>'','Ы'=>'y','Ь'=>'','Э'=>'e','Ю'=>'yu','Я'=>'ya',' '=>'-'
    ];
    $str = strtr((string)$str, $translit);
    $str = strtolower($str);
    $str = preg_replace('/[^a-z0-9_\-]/', '', $str);
    $str = preg_replace('/-+/', '-', trim($str, '-'));
    return $str ?: 'section';
}

class WP_Error {
    public $code;
    public $message;
    public $data;
    public function __construct($code = '', $message = '', $data = []) {
        $this->code = $code;
        $this->message = $message;
        $this->data = $data;
    }
    public function get_error_data() {
        return $this->data;
    }
}
function is_wp_error($thing) {
    return $thing instanceof WP_Error;
}

class WP_REST_Request implements ArrayAccess {
    public $method;
    public $route;
    public $params = [];
    public function __construct($method, $route) {
        $this->method = $method;
        $this->route = $route;
    }
    public function set_param($k, $v) { $this->params[$k] = $v; }
    public function get_param($k) { return $this->params[$k] ?? null; }
    public function get_params() { return $this->params; }
    public function get_json_params() { return $this->params; }
    #[\ReturnTypeWillChange]
    public function offsetExists($offset) { return isset($this->params[$offset]); }
    #[\ReturnTypeWillChange]
    public function offsetGet($offset) { return $this->params[$offset] ?? null; }
    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value) { $this->params[$offset] = $value; }
    #[\ReturnTypeWillChange]
    public function offsetUnset($offset) { unset($this->params[$offset]); }
}

class WP_REST_Response {
    public $data;
    public $status;
    public function __construct($data = null, $status = 200) {
        $this->data = $data;
        $this->status = $status;
    }
}
function rest_ensure_response($response) {
    if ($response instanceof WP_REST_Response) return $response;
    return new WP_REST_Response($response, 200);
}

function register_rest_route($namespace, $route, $args) {
    global $mock_rest_routes;
    $mock_rest_routes[$namespace . $route] = $args;
}

$registered_post_meta = [];
function register_post_meta($post_type, $meta_key, $args) {
    global $registered_post_meta;
    $registered_post_meta[$meta_key] = $args;
}

// Load plugin and theme code under test
require_once __DIR__ . '/../wp-content/plugins/tchp-content/tchp-content.php';
require_once __DIR__ . '/../theme4/includes/functions.php';

// Trigger init & rest_api_init
foreach ($mock_hooks['init'] as $cb) { $cb(); }
foreach ($mock_hooks['rest_api_init'] as $cb) { $cb(); }

$tests_passed = 0;
$tests_total = 0;

function assert_test($description, $condition) {
    global $tests_passed, $tests_total;
    $tests_total++;
    if ($condition) {
        $tests_passed++;
        echo "  [PASS] {$description}\n";
    } else {
        echo "  [FAIL] {$description}\n";
    }
}

echo "\n=======================================================\n";
echo "    PACKAGE A.1 BUSINESS LOGIC & CONTRACTS UNIT TESTS  \n";
echo "              (Mock WordPress Environment)             \n";
echo "=======================================================\n";

// Set up sample post 101 in database
$mock_posts[101] = (object)[
    'ID' => 101,
    'post_title' => 'Пайка SMD компонентов 0805',
    'post_content' => '<p>Инженерное руководство по монтажу чип-компонентов.</p>',
    'post_excerpt' => 'Краткое инженерное резюме статьи.',
    'post_type' => 'post',
];
$mock_meta[101] = [
    'tchp_solder_alloy' => 'pos61',
    'tchp_temp_kind' => 'joint_temperature',
    'tchp_temp_range' => '210–230 °C',
    'tchp_temp_peak' => '245 °C',
    'tchp_standard' => 'IPC-A-610G',
    'tchp_standard_class' => 'КЛАСС 2',
    'tchp_standard_description' => 'Смещение вывода не более 25%',
    'tchp_tools' => [['name' => 'Микроскоп', 'model' => 'MBS-10', 'purpose' => 'ОТК контроль']],
    'tchp_faq_items' => [['q' => 'Как отмывать флюс?', 'a' => 'Изопропиловым спиртом высокой очистки.']],
    'tchp_sources' => [['title' => 'IPC-A-610G', 'standard' => 'IPC', 'url' => 'https://ipc.org']],
    'tchp_review_status' => 'unreviewed',
];

// 1. REAL REST API SIMULATOR FUNCTION
function dispatch_mock_rest_update($post_id, $payload) {
    global $mock_hooks, $mock_posts, $mock_meta;
    $req = new WP_REST_Request('POST', "/wp/v2/posts/{$post_id}");
    foreach ($payload as $k => $v) {
        $req->set_param($k, $v);
    }

    // Step 1: rest_pre_dispatch hook (pre-normalization)
    foreach ($mock_hooks['rest_pre_dispatch'] as $cb) {
        $cb(null, null, $req);
    }

    // Step 2: rest_pre_insert_post filter (validation before DB write)
    $prepared = new stdClass();
    foreach ($mock_hooks['rest_pre_insert_post'] as $cb) {
        $res = $cb($prepared, $req);
        if (is_wp_error($res)) {
            return $res; // Rejection! No DB changes!
        }
    }

    // Step 3: DB writes (only reached if validation passed)
    if (isset($payload['meta']) && is_array($payload['meta'])) {
        foreach ($payload['meta'] as $meta_key => $meta_val) {
            update_post_meta($post_id, $meta_key, $meta_val);
        }
    }
    if (isset($payload['post_content'])) {
        $mock_posts[$post_id]->post_content = $payload['post_content'];
    }
    if (isset($payload['post_title'])) {
        $mock_posts[$post_id]->post_title = $payload['post_title'];
    }

    // Step 4: post-save check
    foreach ($mock_hooks['rest_after_insert_post'] as $cb) {
        $cb($mock_posts[$post_id], $req, false);
    }

    return new WP_REST_Response(['success' => true], 200);
}

// -----------------------------------------------------------------------------
// SCENARIO 1: Empty or whitespace FAQ rejection (400) without clearing existing data
// -----------------------------------------------------------------------------
echo "\nScenario 1: REST Empty/Whitespace FAQ Rejection without Data Loss:\n";
$faq_before = get_post_meta(101, 'tchp_faq_items', true);

// 1.1 Empty q and a
$res1 = dispatch_mock_rest_update(101, [
    'meta' => [
        'tchp_faq_items' => [
            ['q' => '', 'a' => '']
        ]
    ]
]);
assert_test("Empty q/a rejected with WP_Error", is_wp_error($res1));
assert_test("Error status code is 400", is_wp_error($res1) && $res1->data['status'] === 400);
$faq_after1 = get_post_meta(101, 'tchp_faq_items', true);
assert_test("Previously saved FAQ was NOT cleared or mutated", $faq_after1 === $faq_before);

// 1.2 Whitespace-only q and a
$res2 = dispatch_mock_rest_update(101, [
    'meta' => [
        'tchp_faq_items' => [
            ['q' => "   \t  ", 'a' => " \n "]
        ]
    ]
]);
assert_test("Whitespace-only q/a rejected with WP_Error (400)", is_wp_error($res2) && $res2->data['status'] === 400);
$faq_after2 = get_post_meta(101, 'tchp_faq_items', true);
assert_test("Previously saved FAQ remains completely intact", $faq_after2 === $faq_before);

// 1.3 HTML-only tags that become empty after stripping
$res_html = dispatch_mock_rest_update(101, [
    'meta' => [
        'tchp_faq_items' => [
            ['q' => '<b></b>', 'a' => '<p>   </p>']
        ]
    ]
]);
assert_test("HTML tags stripped to empty rejected with WP_Error (400)", is_wp_error($res_html) && $res_html->data['status'] === 400);
$faq_after_html = get_post_meta(101, 'tchp_faq_items', true);
assert_test("Previously saved FAQ remains intact after HTML-stripped attempt", $faq_after_html === $faq_before);

// -----------------------------------------------------------------------------
// SCENARIO 2: Unknown solder alloy rejected with 400
// -----------------------------------------------------------------------------
echo "\nScenario 2: Unknown Solder Alloy Rejection:\n";
$alloy_before = get_post_meta(101, 'tchp_solder_alloy', true);
$res_alloy = dispatch_mock_rest_update(101, [
    'meta' => [
        'tchp_solder_alloy' => 'unknown_flux_compound_99'
    ]
]);
assert_test("Unknown solder alloy rejected with WP_Error (400)", is_wp_error($res_alloy) && $res_alloy->data['status'] === 400);
assert_test("Stored solder alloy remained pos61 without corruption", get_post_meta(101, 'tchp_solder_alloy', true) === $alloy_before);

// -----------------------------------------------------------------------------
// SCENARIO 3: Partial REST update without tchp_faq_items leaves FAQ untouched
// -----------------------------------------------------------------------------
echo "\nScenario 3: Partial REST Updates:\n";
$res_partial = dispatch_mock_rest_update(101, [
    'meta' => [
        'tchp_difficulty' => 'Эксперт'
    ]
]);
assert_test("Partial update succeeded with 200 OK", !is_wp_error($res_partial) && $res_partial->status === 200);
assert_test("Updated field tchp_difficulty changed to 'Эксперт'", get_post_meta(101, 'tchp_difficulty', true) === 'Эксперт');
assert_test("Omitted tchp_faq_items was NOT erased", get_post_meta(101, 'tchp_faq_items', true) === $faq_before);

// -----------------------------------------------------------------------------
// SCENARIO 4: Verification call with outdated / mismatching expected_hash -> 409 Conflict
// -----------------------------------------------------------------------------
echo "\nScenario 4: Verification with Outdated expected_hash (409 Conflict):\n";
$actual_hash = tchp_calculate_article_hash(101);

// Call verify endpoint with wrong/outdated hash
$verify_route_cb = $mock_rest_routes['tchp/v1/posts/(?P<id>\d+)/verify']['callback'];
$verify_req_stale = new WP_REST_Request('POST', '/tchp/v1/posts/101/verify');
$verify_req_stale->set_param('id', 101);
$verify_req_stale->set_param('expected_hash', 'stale_hash_from_older_revision_1234567890');

$verify_res_stale = $verify_route_cb($verify_req_stale);
assert_test("Outdated expected_hash returns WP_Error", is_wp_error($verify_res_stale));
assert_test("Status code is strictly 409 Conflict", is_wp_error($verify_res_stale) && $verify_res_stale->data['status'] === 409);
assert_test("Status remained unreviewed", get_post_meta(101, 'tchp_review_status', true) === 'unreviewed');
assert_test("No reviewer or reviewed_content_hash set", empty(get_post_meta(101, 'tchp_reviewed_content_hash', true)));

// Call verify endpoint with missing expected_hash
$verify_req_missing = new WP_REST_Request('POST', '/tchp/v1/posts/101/verify');
$verify_req_missing->set_param('id', 101);
$verify_res_missing = $verify_route_cb($verify_req_missing);
assert_test("Missing expected_hash rejected with 400 Bad Request", is_wp_error($verify_res_missing) && $verify_res_missing->data['status'] === 400);

// -----------------------------------------------------------------------------
// SCENARIO 5: Verification with valid expected_hash -> 200 OK & Verified
// -----------------------------------------------------------------------------
echo "\nScenario 5: Verification with Valid expected_hash (200 OK):\n";
$verify_req_valid = new WP_REST_Request('POST', '/tchp/v1/posts/101/verify');
$verify_req_valid->set_param('id', 101);
$verify_req_valid->set_param('expected_hash', $actual_hash);

$verify_res_valid = $verify_route_cb($verify_req_valid);
assert_test("Valid expected_hash returns HTTP 200 OK", !is_wp_error($verify_res_valid) && $verify_res_valid->status === 200);
assert_test("Post review status is now 'verified'", get_post_meta(101, 'tchp_review_status', true) === 'verified');
assert_test("Server recorded reviewer_id = 42", get_post_meta(101, 'tchp_reviewer_id', true) === 42);
assert_test("Server recorded matching reviewed_content_hash", get_post_meta(101, 'tchp_reviewed_content_hash', true) === $actual_hash);

$editorial = tchp_get_editorial_data(101);
assert_test("Editorial data is_verified is TRUE", $editorial['is_verified'] === true);

// -----------------------------------------------------------------------------
// SCENARIO 6: Content modification after verification triggers outdated status
// -----------------------------------------------------------------------------
echo "\nScenario 6: Outdated Status on Content Change:\n";
dispatch_mock_rest_update(101, [
    'post_content' => '<p>Новая редакция текста после того, как ОТК уже подтвердил статью!</p>'
]);

$editorial_after_edit = tchp_get_editorial_data(101);
assert_test("Modified post has is_verified = FALSE", $editorial_after_edit['is_verified'] === false);
assert_test("Modified post has is_outdated = TRUE", $editorial_after_edit['is_outdated'] === true);
assert_test("Post status automatically downgraded to 'in_review'", get_post_meta(101, 'tchp_review_status', true) === 'in_review');

// -----------------------------------------------------------------------------
// SCENARIO 7: Document-wide anchor uniqueness (Section vs H2 collision & non-H2 duplicate IDs)
// -----------------------------------------------------------------------------
echo "\nScenario 7: Document-Wide Anchor Uniqueness (No DOM duplicates):\n";

// Complex HTML containing section id, div id, duplicate non-H2 ID, duplicate H2, template clash, and untitled H2
$input_html = '
<section id="preparation">
  <div id="step-one">
    <h2 id="preparation">Подготовка рабочего места</h2>
  </div>
  <p>Описание шага подготовки.</p>
</section>
<section id="step-one">
  <h2 id="step-one">Пайка выводов</h2>
  <h2>Пайка выводов</h2>
  <h2 id="main-content">Контроль пайки</h2>
</section>
';

$article_toc = [];
$detected_duplicates = [];
$output_html = tchp_process_article_anchors($input_html, $article_toc, $detected_duplicates);

// Extract ALL IDs across the whole output HTML document
preg_match_all('/\bid=(["\'])([^"\']+)\1/i', $output_html, $final_dom_id_matches);
$all_dom_ids = $final_dom_id_matches[2];

assert_test("Section id 'preparation' and H2 id collision resolved to 'preparation-2'", strpos($output_html, 'id="preparation-2"') !== false);
assert_test("Duplicate non-H2 ID 'step-one' resolved to 'step-one-2'", strpos($output_html, 'id="step-one-2"') !== false);
assert_test("Duplicate non-H2 collision recorded in audit detected_duplicates", in_array('step-one', $detected_duplicates, true));
assert_test("Duplicate H2 title resolved to next free candidate", strpos($output_html, 'id="payka-vyvodov"') !== false);
assert_test("Template id 'main-content' collision resolved to 'main-content-2'", strpos($output_html, 'id="main-content-2"') !== false);

// Global DOM uniqueness check:
$unique_dom_ids = array_unique($all_dom_ids);
assert_test("ALL IDs in generated DOM are 100% unique (no duplicates)", count($all_dom_ids) === count($unique_dom_ids));
assert_test("Theme canonical function tchp_process_article_anchors was executed directly", count($article_toc) === 4);

// -----------------------------------------------------------------------------
// SCENARIO 8: Canonical alloy normalization
// -----------------------------------------------------------------------------
echo "\nScenario 8: Canonical Alloy Normalization:\n";
assert_test("wood -> vuda", tchp_normalize_solder_alloy('wood') === 'vuda');
assert_test("rose -> roze", tchp_normalize_solder_alloy('rose') === 'roze');
assert_test("universal preserved", tchp_normalize_solder_alloy('universal') === 'universal');
assert_test("pos61 preserved", tchp_normalize_solder_alloy('pos61') === 'pos61');

// -----------------------------------------------------------------------------
// SCENARIO 9: Rank Math Schema Detection
// -----------------------------------------------------------------------------
echo "\nScenario 9: Rank Math Schema Owner Detection:\n";
class RankMath {}
$mock_options['rank_math_modules'] = ['rich-snippet', 'sitemap'];
assert_test("Rank Math rich-snippet active -> Theme schema suppressed", tchp_should_render_theme_schema(101) === false);
$mock_options['rank_math_modules'] = ['sitemap'];
assert_test("Rank Math rich-snippet inactive -> Theme schema active fallback", tchp_should_render_theme_schema(101) === true);

// -----------------------------------------------------------------------------
// SCENARIO 10: Canonical Fingerprint Parity under Key Reordering & Missing Optional Fields
// -----------------------------------------------------------------------------
echo "\nScenario 10: Canonical Fingerprint Parity (Key Reordering, Missing Fields, Cross-Language Parity):\n";

// 10.1 Tool key reordering invariance
$art1 = ['title' => 'T', 'content' => 'C', 'excerpt' => 'E', 'tools' => [['name' => 'Iron', 'model' => 'T12', 'purpose' => 'Soldering']], 'solder_alloy' => 'pos61'];
$art2 = ['title' => 'T', 'content' => 'C', 'excerpt' => 'E', 'tools' => [['purpose' => 'Soldering', 'name' => 'Iron', 'model' => 'T12']], 'solder_alloy' => 'pos61'];
$h1 = tchp_calculate_article_hash($art1);
$h2 = tchp_calculate_article_hash($art2);
assert_test("Tool key reordering produces identical fingerprint", $h1 === $h2);

// 10.2 Missing optional tool fields match empty strings default
$art3 = ['title' => 'T', 'content' => 'C', 'excerpt' => 'E', 'tools' => [['name' => 'Iron']], 'solder_alloy' => 'pos61'];
$art4 = ['title' => 'T', 'content' => 'C', 'excerpt' => 'E', 'tools' => [['name' => 'Iron', 'model' => '', 'purpose' => '']], 'solder_alloy' => 'pos61'];
$h3 = tchp_calculate_article_hash($art3);
$h4 = tchp_calculate_article_hash($art4);
assert_test("Missing optional tool fields match empty strings default", $h3 === $h4);

// 10.3 Sources key reordering & missing optional fields
$art5 = ['title' => 'T', 'content' => 'C', 'excerpt' => 'E', 'sources' => [['url' => 'https://ipc.org', 'title' => 'IPC']], 'solder_alloy' => 'pos61'];
$art6 = ['title' => 'T', 'content' => 'C', 'excerpt' => 'E', 'sources' => [['title' => 'IPC', 'standard' => '', 'url' => 'https://ipc.org']], 'solder_alloy' => 'pos61'];
$h5 = tchp_calculate_article_hash($art5);
$h6 = tchp_calculate_article_hash($art6);
assert_test("Source key reordering & missing fields match empty string default", $h5 === $h6);

// 10.4 FAQ key reordering
$art7 = ['title' => 'T', 'content' => 'C', 'excerpt' => 'E', 'faq' => [['a' => 'Ans', 'q' => 'Que']], 'solder_alloy' => 'pos61'];
$art8 = ['title' => 'T', 'content' => 'C', 'excerpt' => 'E', 'faq' => [['q' => 'Que', 'a' => 'Ans']], 'solder_alloy' => 'pos61'];
$h7 = tchp_calculate_article_hash($art7);
$h8 = tchp_calculate_article_hash($art8);
assert_test("FAQ key reordering produces identical fingerprint", $h7 === $h8);

// 10.5 Cross-language byte-exact reference test vector parity with Python delivery_cli
$canonical_vector = [
    'title' => 'Руководство по монтажу',
    'content_html' => '<p>Текст статьи</p>',
    'excerpt' => 'Лид статьи',
    'tools' => [['name' => 'Паяльник T12', 'model' => 'T12-D24', 'purpose' => 'Монтаж']],
    'faq' => [['q' => 'Температура?', 'a' => '230 °C']],
    'sources' => [['title' => 'ГОСТ 21931-76', 'standard' => 'ГОСТ', 'url' => 'https://gost.ru']],
    'solder_alloy' => 'pos61',
    'temp_data' => ['kind' => 'joint_temperature', 'range' => '210–230 °C', 'peak' => '245 °C'],
    'standard_data' => ['standard' => 'IPC-A-610G', 'class' => 'КЛАСС 2', 'description' => 'Электронные изделия']
];
$vector_hash = tchp_calculate_article_hash($canonical_vector);
$expected_python_hash = 'da915292ff91fd3c61973ce5e6e9012bc0d1e1f80770b677663f63cdee4977b0';
assert_test("PHP tchp_calculate_article_hash matches Python compute_canonical_fingerprint byte-exact", $vector_hash === $expected_python_hash);

echo "\n=======================================================\n";
echo "SUMMARY: {$tests_passed} / {$tests_total} tests passed.\n";
if ($tests_passed === $tests_total) {
    echo "VERDICT: ALL TESTS PASSED! PACKAGE A.1 BUSINESS LOGIC & CONTRACTS VERIFIED.\n";
    exit(0);
} else {
    echo "VERDICT: TEST SUITE FAILED.\n";
    exit(1);
}
