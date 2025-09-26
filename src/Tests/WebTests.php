<?php

namespace SocExtAffairs\ReservationDataAnalysis\Tests;

use SocExtAffairs\ReservationDataAnalysis\Entity\Reservation;
use Twig\Environment;

class WebTests {
    private Environment $twig;
    
    public function __construct(Environment $twig) {
        $this->twig = $twig;
    }
    
    public function runAll(): array {
        $tests = [
            $this->testDatabaseConnectivity(),
            $this->testOrmFunctionality(),
            $this->testTemplateRendering(),
            $this->testFileUploadCapabilities(),
            $this->testSecurityChecks(),
            $this->testDuplicateDetection(),
            $this->testPaginationSorting(),
            $this->testReportsGeneration(),
            $this->testHashGeneration(),
            $this->testDataValidation()
        ];
        
        $passed = count(array_filter($tests, fn($test) => $test['status'] === 'passed'));
        
        return [
            'tests' => $tests,
            'passed' => $passed,
            'total' => count($tests)
        ];
    }
    
    private function testDatabaseConnectivity(): array {
        try {
            global $wpdb;
            $result = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'reservation_data'");
            return [
                'name' => 'Database Connectivity',
                'status' => 'passed',
                'message' => "Successfully connected. Found {$result} reservation records.",
                'details' => "Query executed: SELECT COUNT(*) FROM {$wpdb->posts}"
            ];
        } catch (\Exception $e) {
            return [
                'name' => 'Database Connectivity',
                'status' => 'failed',
                'message' => 'Database connection failed: ' . $e->getMessage(),
                'details' => $e->getTraceAsString()
            ];
        }
    }
    
    private function testOrmFunctionality(): array {
        try {
            $testReservation = new Reservation('Test Group', '2024-01-01', 'Test Location', 2);
            $originalCount = count(getAllReservations());
            
            saveReservation($testReservation);
            $newCount = count(getAllReservations());
            
            if ($newCount === $originalCount + 1) {
                // Clean up
                $reservations = getAllReservations();
                foreach ($reservations as $res) {
                    if ($res->getGroupName() === 'Test Group') {
                        deleteReservation($res->getId());
                        break;
                    }
                }
                
                return [
                    'name' => 'ORM Functionality',
                    'status' => 'passed',
                    'message' => 'CRUD operations working correctly',
                    'details' => "Created and deleted test reservation. Count: {$originalCount} -> {$newCount} -> " . count(getAllReservations())
                ];
            } else {
                return [
                    'name' => 'ORM Functionality',
                    'status' => 'failed',
                    'message' => 'Save operation failed',
                    'details' => "Expected count increase from {$originalCount} to " . ($originalCount + 1) . ", got {$newCount}"
                ];
            }
        } catch (\Exception $e) {
            return [
                'name' => 'ORM Functionality',
                'status' => 'failed',
                'message' => 'ORM test failed: ' . $e->getMessage(),
                'details' => $e->getTraceAsString()
            ];
        }
    }
    
    private function testTemplateRendering(): array {
        try {
            $output = $this->twig->render('reservations/create.html.twig');
            
            if (strpos($output, 'Group Name') !== false && strpos($output, 'form') !== false) {
                return [
                    'name' => 'Template Rendering',
                    'status' => 'passed',
                    'message' => 'Twig templates rendering correctly',
                    'details' => 'Successfully rendered create.html.twig with expected content'
                ];
            } else {
                return [
                    'name' => 'Template Rendering',
                    'status' => 'failed',
                    'message' => 'Template content missing expected elements',
                    'details' => 'Output length: ' . strlen($output) . ' characters'
                ];
            }
        } catch (\Exception $e) {
            return [
                'name' => 'Template Rendering',
                'status' => 'failed',
                'message' => 'Template rendering failed: ' . $e->getMessage(),
                'details' => $e->getTraceAsString()
            ];
        }
    }
    
    private function testFileUploadCapabilities(): array {
        try {
            $uploadDir = wp_upload_dir();
            $testFile = $uploadDir['basedir'] . '/test_write.txt';
            
            if (file_put_contents($testFile, 'test') !== false) {
                unlink($testFile);
                
                if (class_exists('PhpOffice\\PhpSpreadsheet\\IOFactory')) {
                    return [
                        'name' => 'File Upload Capabilities',
                        'status' => 'passed',
                        'message' => 'File system writable and PhpSpreadsheet available',
                        'details' => "Upload directory: {$uploadDir['basedir']}"
                    ];
                } else {
                    return [
                        'name' => 'File Upload Capabilities',
                        'status' => 'warning',
                        'message' => 'File system writable but PhpSpreadsheet missing',
                        'details' => 'Excel upload functionality may not work'
                    ];
                }
            } else {
                return [
                    'name' => 'File Upload Capabilities',
                    'status' => 'failed',
                    'message' => 'Upload directory not writable',
                    'details' => "Cannot write to: {$uploadDir['basedir']}"
                ];
            }
        } catch (\Exception $e) {
            return [
                'name' => 'File Upload Capabilities',
                'status' => 'failed',
                'message' => 'File upload test failed: ' . $e->getMessage(),
                'details' => $e->getTraceAsString()
            ];
        }
    }
    
    private function testSecurityChecks(): array {
        try {
            $checks = [];
            
            if (current_user_can('manage_options')) {
                $checks[] = 'User capabilities: OK';
            } else {
                $checks[] = 'User capabilities: FAILED';
            }
            
            $nonce = wp_create_nonce('test_action');
            if (wp_verify_nonce($nonce, 'test_action')) {
                $checks[] = 'Nonce verification: OK';
            } else {
                $checks[] = 'Nonce verification: FAILED';
            }
            
            $test_input = '<script>alert("xss")</script>';
            $sanitized = sanitize_text_field($test_input);
            if ($sanitized !== $test_input) {
                $checks[] = 'Input sanitization: OK';
            } else {
                $checks[] = 'Input sanitization: FAILED';
            }
            
            $failed = array_filter($checks, fn($check) => strpos($check, 'FAILED') !== false);
            
            if (empty($failed)) {
                return [
                    'name' => 'Security Checks',
                    'status' => 'passed',
                    'message' => 'All security checks passed',
                    'details' => implode("\n", $checks)
                ];
            } else {
                return [
                    'name' => 'Security Checks',
                    'status' => 'failed',
                    'message' => count($failed) . ' security check(s) failed',
                    'details' => implode("\n", $checks)
                ];
            }
        } catch (\Exception $e) {
            return [
                'name' => 'Security Checks',
                'status' => 'failed',
                'message' => 'Security test failed: ' . $e->getMessage(),
                'details' => $e->getTraceAsString()
            ];
        }
    }
    
    private function testDuplicateDetection(): array {
        try {
            $testReservation = new Reservation('Duplicate Test', '2024-01-01', 'Test Room', 1);
            $originalCount = count(getAllReservations());
            
            saveReservation($testReservation);
            $hash = $testReservation->getHash();
            $existingHashes = getExistingHashes();
            
            if (in_array($hash, $existingHashes)) {
                // Clean up
                $reservations = getAllReservations();
                foreach ($reservations as $res) {
                    if ($res->getGroupName() === 'Duplicate Test') {
                        deleteReservation($res->getId());
                        break;
                    }
                }
                
                return [
                    'name' => 'Duplicate Detection',
                    'status' => 'passed',
                    'message' => 'Hash generation and duplicate detection working',
                    'details' => "Generated hash: {$hash}"
                ];
            } else {
                return [
                    'name' => 'Duplicate Detection',
                    'status' => 'failed',
                    'message' => 'Hash not found in existing hashes',
                    'details' => "Hash: {$hash}, Existing count: " . count($existingHashes)
                ];
            }
        } catch (\Exception $e) {
            return [
                'name' => 'Duplicate Detection',
                'status' => 'failed',
                'message' => 'Duplicate detection test failed: ' . $e->getMessage(),
                'details' => $e->getTraceAsString()
            ];
        }
    }
    
    private function testPaginationSorting(): array {
        try {
            $result = getAllReservationsPaginated(1, 5, 'groupName', 'asc');
            
            if (isset($result['reservations']) && isset($result['total_pages']) && isset($result['orderby'])) {
                return [
                    'name' => 'Pagination & Sorting',
                    'status' => 'passed',
                    'message' => 'Pagination and sorting functions working',
                    'details' => "Page 1, 5 per page, sorted by groupName ASC. Total pages: {$result['total_pages']}"
                ];
            } else {
                return [
                    'name' => 'Pagination & Sorting',
                    'status' => 'failed',
                    'message' => 'Missing required pagination fields',
                    'details' => 'Keys: ' . implode(', ', array_keys($result))
                ];
            }
        } catch (\Exception $e) {
            return [
                'name' => 'Pagination & Sorting',
                'status' => 'failed',
                'message' => 'Pagination test failed: ' . $e->getMessage(),
                'details' => $e->getTraceAsString()
            ];
        }
    }
    
    private function testReportsGeneration(): array {
        try {
            $reports = generateReports();
            
            if (isset($reports['group_usage']) && isset($reports['space_usage']) && isset($reports['total_reservations'])) {
                return [
                    'name' => 'Reports Generation',
                    'status' => 'passed',
                    'message' => 'Report generation working correctly',
                    'details' => "Total reservations: {$reports['total_reservations']}, Groups: " . count($reports['group_usage']) . ", Spaces: " . count($reports['space_usage'])
                ];
            } else {
                return [
                    'name' => 'Reports Generation',
                    'status' => 'failed',
                    'message' => 'Missing required report fields',
                    'details' => 'Keys: ' . implode(', ', array_keys($reports))
                ];
            }
        } catch (\Exception $e) {
            return [
                'name' => 'Reports Generation',
                'status' => 'failed',
                'message' => 'Reports test failed: ' . $e->getMessage(),
                'details' => $e->getTraceAsString()
            ];
        }
    }
    
    private function testHashGeneration(): array {
        try {
            $reservation1 = new Reservation('Test', '2024-01-01', 'Room A', 2);
            $reservation2 = new Reservation('Test', '2024-01-01', 'Room A', 2);
            $reservation3 = new Reservation('Test', '2024-01-01', 'Room B', 2);
            
            $hash1 = $reservation1->getHash();
            $hash2 = $reservation2->getHash();
            $hash3 = $reservation3->getHash();
            
            if ($hash1 === $hash2 && $hash1 !== $hash3) {
                return [
                    'name' => 'Hash Generation',
                    'status' => 'passed',
                    'message' => 'Hash generation consistent and unique',
                    'details' => "Identical: {$hash1} vs {$hash2}, Different: {$hash3}"
                ];
            } else {
                return [
                    'name' => 'Hash Generation',
                    'status' => 'failed',
                    'message' => 'Hash generation inconsistent',
                    'details' => "Hash1: {$hash1}, Hash2: {$hash2}, Hash3: {$hash3}"
                ];
            }
        } catch (\Exception $e) {
            return [
                'name' => 'Hash Generation',
                'status' => 'failed',
                'message' => 'Hash generation test failed: ' . $e->getMessage(),
                'details' => $e->getTraceAsString()
            ];
        }
    }
    
    private function testDataValidation(): array {
        try {
            $checks = [];
            
            // Test empty reservation retrieval
            $nonExistent = getReservation(999999);
            if ($nonExistent === null) {
                $checks[] = 'Non-existent reservation handling: OK';
            } else {
                $checks[] = 'Non-existent reservation handling: FAILED';
            }
            
            // Test reservation entity validation
            $testReservation = new Reservation('Valid Group', '2024-01-01', 'Valid Location', 3);
            if ($testReservation->getGroupName() === 'Valid Group' && $testReservation->getDuration() === 3) {
                $checks[] = 'Entity data integrity: OK';
            } else {
                $checks[] = 'Entity data integrity: FAILED';
            }
            
            // Test hash consistency
            $hash1 = $testReservation->getHash();
            $hash2 = $testReservation->getHash();
            if ($hash1 === $hash2 && !empty($hash1)) {
                $checks[] = 'Hash consistency: OK';
            } else {
                $checks[] = 'Hash consistency: FAILED';
            }
            
            $failed = array_filter($checks, fn($check) => strpos($check, 'FAILED') !== false);
            
            if (empty($failed)) {
                return [
                    'name' => 'Data Validation',
                    'status' => 'passed',
                    'message' => 'All data validation checks passed',
                    'details' => implode("\n", $checks)
                ];
            } else {
                return [
                    'name' => 'Data Validation',
                    'status' => 'failed',
                    'message' => count($failed) . ' validation check(s) failed',
                    'details' => implode("\n", $checks)
                ];
            }
        } catch (\Exception $e) {
            return [
                'name' => 'Data Validation',
                'status' => 'failed',
                'message' => 'Data validation test failed: ' . $e->getMessage(),
                'details' => $e->getTraceAsString()
            ];
        }
    }
}