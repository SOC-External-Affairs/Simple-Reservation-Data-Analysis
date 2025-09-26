<?php
/**
 * Plugin Name: Simple Reservation Data Analysis
 * Description: Simple WordPress plugin with ORM and templating
 * Version: 1.0.0
 * Author: RA
 */

if (!defined('ABSPATH')) {
    return;
}

$autoload_path = plugin_dir_path(__FILE__) . 'vendor/autoload.php';
if (!file_exists($autoload_path) || !is_readable($autoload_path)) {
    wp_die('Required dependencies not found.');
}
require_once $autoload_path;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;
use SocExtAffairs\ReservationDataAnalysis\Entity\Reservation;
use SocExtAffairs\ReservationDataAnalysis\Tests\WebTests;
use PhpOffice\PhpSpreadsheet\IOFactory;

// Setup Twig
$loader = new FilesystemLoader(__DIR__ . '/templates');
$twig = new Environment($loader);
$twig->addFunction(new TwigFunction('admin_url', 'admin_url'));
$twig->addFunction(new TwigFunction('wp_nonce_field', function($action, $name = '_wpnonce') {
    return wp_nonce_field($action, $name, true, false);
}, ['is_safe' => ['html']]));

// Plugin activation hook
register_activation_hook(__FILE__, function() {
    $plugin_dir = plugin_dir_path(__FILE__);
    
    // Run composer update if composer.json exists
    if (file_exists($plugin_dir . 'composer.json')) {
        $composer_cmd = 'cd ' . escapeshellarg($plugin_dir) . ' && composer install --no-dev --optimize-autoloader 2>&1';
        $output = shell_exec($composer_cmd);
        
        // Log result for debugging
        error_log('Composer install output: ' . $output);
    }
});

// Register post type
add_action('init', function() {
    register_post_type('reservation_data', [
        'public' => false,
        'show_ui' => false,
        'supports' => ['title']
    ]);
});

// Admin menu
add_action('admin_menu', function() use ($twig) {
    add_menu_page(
        'Reservations',
        'Reservations',
        'manage_options',
        'reservations',
        function() use ($twig) {
            $action = sanitize_text_field($_GET['action'] ?? 'index');
            
            if ($action === 'create') {
                echo $twig->render('reservations/create.html.twig');
            } elseif ($action === 'edit') {
                $id = intval($_GET['id'] ?? 0);
                if ($id > 0) {
                    $reservation = getReservation($id);
                    echo $twig->render('reservations/edit.html.twig', ['reservation' => $reservation]);
                } else {
                    wp_safe_redirect(admin_url('admin.php?page=reservations'));
                    return;
                }
            } elseif ($action === 'upload') {
                echo $twig->render('reservations/upload.html.twig');
            } elseif ($action === 'upload_result') {
                $result = get_transient('upload_result_' . get_current_user_id());
                if ($result) {
                    delete_transient('upload_result_' . get_current_user_id());
                    echo $twig->render('reservations/upload-result.html.twig', $result);
                } else {
                    wp_redirect(admin_url('admin.php?page=reservations'));
                }
            } else {
                $page = max(1, intval($_GET['paged'] ?? 1));
                $per_page = 20;
                $orderby = sanitize_text_field($_GET['orderby'] ?? 'date');
                $order = sanitize_text_field($_GET['order'] ?? 'desc');
                $result = getAllReservationsPaginated($page, $per_page, $orderby, $order);
                echo $twig->render('reservations/index.html.twig', $result);
            }
        },
        'dashicons-calendar-alt',
        30
    );
    
    add_submenu_page(
        'reservations',
        'Reports',
        'Reports',
        'manage_options',
        'reservation-reports',
        function() use ($twig) {
            $reports = generateReports();
            echo $twig->render('reservations/reports.html.twig', $reports);
        }
    );
    
    add_submenu_page(
        'reservations',
        'System Tests',
        'Tests',
        'manage_options',
        'reservation-tests',
        function() use ($twig) {
            $test_results = null;
            if (isset($_POST['run_tests']) && wp_verify_nonce($_POST['_wpnonce'], 'run_tests')) {
                $webTests = new WebTests($twig);
                $test_results = $webTests->runAll();
            }
            echo $twig->render('reservations/tests.html.twig', [
                'test_results' => $test_results,
                'nonce_field' => wp_nonce_field('run_tests', '_wpnonce', true, false)
            ]);
        }
    );
});

// Admin bar menu
add_action('admin_bar_menu', function($wp_admin_bar) {
    if (!current_user_can('manage_options')) return;
    
    $wp_admin_bar->add_menu([
        'id' => 'reservations',
        'title' => 'Reservations',
        'href' => admin_url('admin.php?page=reservations')
    ]);
    
    $wp_admin_bar->add_menu([
        'id' => 'reservations-add',
        'parent' => 'reservations',
        'title' => 'Add New',
        'href' => admin_url('admin.php?page=reservations&action=create')
    ]);
    
    $wp_admin_bar->add_menu([
        'id' => 'reservations-upload',
        'parent' => 'reservations',
        'title' => 'Bulk Upload',
        'href' => admin_url('admin.php?page=reservations&action=upload')
    ]);
    
    $wp_admin_bar->add_menu([
        'id' => 'reservations-reports',
        'parent' => 'reservations',
        'title' => 'Reports',
        'href' => admin_url('admin.php?page=reservation-reports')
    ]);
    
    $wp_admin_bar->add_menu([
        'id' => 'reservations-tests',
        'parent' => 'reservations',
        'title' => 'Tests',
        'href' => admin_url('admin.php?page=reservation-tests')
    ]);
}, 100);

// Handle form submissions
add_action('admin_post_create_reservation', function() {
    if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['_wpnonce'], 'create_reservation')) {
        wp_die('Unauthorized');
    }
    
    $reservation = new Reservation(
        sanitize_text_field($_POST['groupName']),
        sanitize_text_field($_POST['dateOfEvent']),
        sanitize_text_field($_POST['locationName']),
        intval($_POST['duration'])
    );
    
    saveReservation($reservation);
    wp_safe_redirect(admin_url('admin.php?page=reservations&message=created'));
    return;
});

add_action('admin_post_update_reservation', function() {
    if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['_wpnonce'], 'update_reservation')) {
        wp_die('Unauthorized');
    }
    
    updateReservation(
        intval($_POST['id']),
        sanitize_text_field($_POST['groupName']),
        sanitize_text_field($_POST['dateOfEvent']),
        sanitize_text_field($_POST['locationName']),
        intval($_POST['duration'])
    );
    
    wp_safe_redirect(admin_url('admin.php?page=reservations&message=updated'));
    return;
});

add_action('admin_post_delete_reservation', function() {
    if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['_wpnonce'], 'delete_reservation')) {
        wp_die('Unauthorized');
    }
    
    deleteReservation(intval($_POST['id']));
    wp_safe_redirect(admin_url('admin.php?page=reservations&message=deleted'));
    return;
});

add_action('admin_post_remove_all_reservations', function() {
    if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['_wpnonce'], 'remove_all_reservations')) {
        wp_die('Unauthorized');
    }
    
    $count = removeAllReservations();
    wp_safe_redirect(admin_url('admin.php?page=reservations&message=removed_all&count=' . $count));
    return;
});

add_action('admin_post_upload_reservations', function() use ($twig) {
    if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['_wpnonce'], 'upload_reservations')) {
        wp_die('Unauthorized');
    }
    
    $result = processExcelUpload($_FILES['excel_file'], $_POST);
    
    set_transient('upload_result_' . get_current_user_id(), $result, 300);
    wp_safe_redirect(admin_url('admin.php?page=reservations&action=upload_result'));
    return;
});

// Simple ORM functions
function getAllReservations(): array {
    $posts = get_posts([
        'post_type' => 'reservation_data',
        'posts_per_page' => -1,
        'post_status' => 'publish'
    ]);
    
    $reservations = [];
    foreach ($posts as $post) {
        $reservation = new Reservation(
            get_post_meta($post->ID, 'groupName', true),
            get_post_meta($post->ID, 'dateOfEvent', true),
            get_post_meta($post->ID, 'locationName', true),
            get_post_meta($post->ID, 'duration', true)
        );
        $reservation->setId($post->ID);
        $reservations[] = $reservation;
    }
    
    return $reservations;
}

function getAllReservationsPaginated(int $page, int $per_page, string $orderby = 'date', string $order = 'desc'): array {
    // Get ALL posts first
    $all_posts = get_posts([
        'post_type' => 'reservation_data',
        'posts_per_page' => -1,
        'post_status' => 'publish'
    ]);
    
    // Convert to reservations
    $all_reservations = [];
    foreach ($all_posts as $post) {
        $reservation = new Reservation(
            get_post_meta($post->ID, 'groupName', true),
            get_post_meta($post->ID, 'dateOfEvent', true),
            get_post_meta($post->ID, 'locationName', true),
            get_post_meta($post->ID, 'duration', true)
        );
        $reservation->setId($post->ID);
        $all_reservations[] = $reservation;
    }
    
    // Sort ALL reservations
    usort($all_reservations, function($a, $b) use ($orderby, $order) {
        $result = 0;
        switch ($orderby) {
            case 'groupName':
                $result = strcasecmp($a->getGroupName(), $b->getGroupName());
                break;
            case 'dateOfEvent':
                $result = strcmp($a->getDateOfEvent(), $b->getDateOfEvent());
                break;
            case 'locationName':
                $result = strcasecmp($a->getLocationName(), $b->getLocationName());
                break;
            case 'duration':
                $result = $a->getDuration() <=> $b->getDuration();
                break;
            default:
                $result = $b->getId() <=> $a->getId();
        }
        return $order === 'asc' ? $result : -$result;
    });
    
    // Paginate the sorted results
    $total = count($all_reservations);
    $total_pages = ceil($total / $per_page);
    $offset = ($page - 1) * $per_page;
    $reservations = array_slice($all_reservations, $offset, $per_page);
    

    
    return [
        'reservations' => $reservations,
        'current_page' => $page,
        'total_pages' => $total_pages,
        'per_page' => $per_page,
        'total_items' => $total,
        'orderby' => $orderby,
        'order' => $order
    ];
}

function getReservation(int $id): ?Reservation {
    $post = get_post($id);
    if (!$post || $post->post_type !== 'reservation_data') return null;
    
    $reservation = new Reservation(
        get_post_meta($id, 'groupName', true),
        get_post_meta($id, 'dateOfEvent', true),
        get_post_meta($id, 'locationName', true),
        get_post_meta($id, 'duration', true)
    );
    $reservation->setId($id);
    return $reservation;
}

function saveReservation(Reservation $reservation): void {
    wp_insert_post([
        'post_type' => 'reservation_data',
        'post_title' => $reservation->getGroupName(),
        'post_status' => 'publish',
        'meta_input' => [
            'groupName' => $reservation->getGroupName(),
            'dateOfEvent' => $reservation->getDateOfEvent(),
            'locationName' => $reservation->getLocationName(),
            'duration' => $reservation->getDuration(),
            'hash' => $reservation->getHash()
        ]
    ]);
}

function updateReservation(int $id, string $groupName, string $dateOfEvent, string $locationName, int $duration): void {
    $reservation = new Reservation($groupName, $dateOfEvent, $locationName, $duration);
    
    wp_update_post([
        'ID' => $id,
        'post_title' => $groupName
    ]);
    
    update_post_meta($id, 'groupName', $groupName);
    update_post_meta($id, 'dateOfEvent', $dateOfEvent);
    update_post_meta($id, 'locationName', $locationName);
    update_post_meta($id, 'duration', $duration);
    update_post_meta($id, 'hash', $reservation->getHash());
}

function deleteReservation(int $id): void {
    wp_delete_post($id, true);
}

function removeAllReservations(): int {
    $reservations = getAllReservations();
    $count = 0;
    
    foreach ($reservations as $reservation) {
        if ($reservation->getId()) {
            deleteReservation($reservation->getId());
            $count++;
        }
    }
    
    return $count;
}

function processExcelUpload(array $file, array $params): array {
    $stats = [
        'total_rows' => 0,
        'inserted' => 0,
        'duplicates' => 0,
        'errors' => 0,
        'error_details' => [],
        'duplicate_details' => [],
        'duplicate_counts' => []
    ];
    
    try {
        $spreadsheet = IOFactory::load($file['tmp_name']);
        $worksheet = $spreadsheet->getActiveSheet();
        $highestRow = $worksheet->getHighestRow();
        $existingHashes = getExistingHashes();
        
        for ($row = intval($params['start_row']); $row <= $highestRow; $row++) {
            $stats['total_rows']++;
            
            try {
                $groupName = trim((string)$worksheet->getCell($params['group_column'] . $row)->getValue());
                $dateOfEvent = $worksheet->getCell($params['date_column'] . $row)->getFormattedValue();
                $locationName = trim((string)$worksheet->getCell($params['location_column'] . $row)->getValue());
                $durationCell = $worksheet->getCell($params['duration_column'] . $row);
                $durationValue = $durationCell->getCalculatedValue();
                if (is_object($durationValue)) {
                    $durationValue = $durationCell->getFormattedValue();
                }
                $duration = intval(preg_replace('/[^0-9.]/', '', (string)$durationValue));
                
                // Skip empty rows (all fields empty)
                if (empty($groupName) && empty($dateOfEvent) && empty($locationName) && $duration <= 0) {
                    $stats['total_rows']--; // Don't count empty rows
                    continue;
                }
                
                $errors = [];
                if (empty($groupName)) $errors[] = "Group Name is missing (value: '{$groupName}')";
                if (empty($dateOfEvent)) $errors[] = "Event Date is missing (value: '{$dateOfEvent}')";
                if (empty($locationName)) $errors[] = "Location is missing (value: '{$locationName}')";
                if ($duration <= 0) $errors[] = "Duration is missing or invalid (value: '{$durationValue}' -> {$duration})";
                
                if (!empty($errors)) {
                    $stats['errors']++;
                    $stats['error_details'][] = "Row {$row}: " . implode(', ', $errors);
                    continue;
                }
                
                $reservation = new Reservation($groupName, $dateOfEvent, $locationName, $duration);
                
                if (in_array($reservation->getHash(), $existingHashes)) {
                    $stats['duplicates']++;
                    $duplicate_key = "{$groupName} | {$dateOfEvent} | {$locationName} | {$duration}hrs";
                    if (!isset($stats['duplicate_counts'][$duplicate_key])) {
                        $stats['duplicate_counts'][$duplicate_key] = ['count' => 0, 'rows' => []];
                    }
                    $stats['duplicate_counts'][$duplicate_key]['count']++;
                    $stats['duplicate_counts'][$duplicate_key]['rows'][] = $row;
                    continue;
                }
                
                saveReservation($reservation);
                $existingHashes[] = $reservation->getHash();
                $stats['inserted']++;
                
            } catch (Exception $e) {
                $stats['errors']++;
                $stats['error_details'][] = "Row {$row}: " . $e->getMessage();
            }
        }
        
    } catch (Exception $e) {
        $stats['errors']++;
        $stats['error_details'][] = 'File processing error: ' . $e->getMessage();
    }
    
    // Clean up uploaded file
    if (file_exists($file['tmp_name'])) {
        unlink($file['tmp_name']);
    }
    
    return $stats;
}

function getExistingHashes(): array {
    global $wpdb;
    
    $results = $wpdb->get_col(
        "SELECT meta_value FROM {$wpdb->postmeta} 
         WHERE meta_key = 'hash' 
         AND post_id IN (
             SELECT ID FROM {$wpdb->posts} 
             WHERE post_type = 'reservation_data' 
             AND post_status = 'publish'
         )"
    );
    
    return $results ?: [];
}

function generateReports(): array {
    $reservations = getAllReservations();
    
    // Group time usage analysis with space breakdown
    $groupUsage = [];
    $groupSpaceUsage = [];
    foreach ($reservations as $reservation) {
        $group = $reservation->getGroupName();
        $location = $reservation->getLocationName();
        
        if (!isset($groupUsage[$group])) {
            $groupUsage[$group] = ['total_time' => 0, 'bookings' => 0];
            $groupSpaceUsage[$group] = [];
        }
        
        $groupUsage[$group]['total_time'] += $reservation->getDuration();
        $groupUsage[$group]['bookings']++;
        
        if (!isset($groupSpaceUsage[$group][$location])) {
            $groupSpaceUsage[$group][$location] = ['total_time' => 0, 'bookings' => 0];
        }
        $groupSpaceUsage[$group][$location]['total_time'] += $reservation->getDuration();
        $groupSpaceUsage[$group][$location]['bookings']++;
    }
    
    // Sort by total time descending
    uasort($groupUsage, function($a, $b) {
        return $b['total_time'] <=> $a['total_time'];
    });
    
    // Sort each group's space usage
    foreach ($groupSpaceUsage as &$spaces) {
        uasort($spaces, function($a, $b) {
            return $b['total_time'] <=> $a['total_time'];
        });
    }
    
    // Space usage analysis
    $spaceUsage = [];
    foreach ($reservations as $reservation) {
        $location = $reservation->getLocationName();
        if (!isset($spaceUsage[$location])) {
            $spaceUsage[$location] = ['total_time' => 0, 'bookings' => 0];
        }
        $spaceUsage[$location]['total_time'] += $reservation->getDuration();
        $spaceUsage[$location]['bookings']++;
    }
    
    // Sort by total time descending
    uasort($spaceUsage, function($a, $b) {
        return $b['total_time'] <=> $a['total_time'];
    });
    
    return [
        'group_usage' => $groupUsage,
        'group_space_usage' => $groupSpaceUsage,
        'space_usage' => $spaceUsage,
        'total_reservations' => count($reservations)
    ];
}

