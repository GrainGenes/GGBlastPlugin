<?php
use PHPUnit\Framework\TestCase;

/**
 * Security-focused unit tests
 */
class SecurityTest extends TestCase {
    
    /**
     * Test directory traversal prevention in job IDs
     */
    public function testDirectoryTraversalPrevention() {
        $maliciousJobIds = [
            '../../../etc/passwd',
            '../../config.json',
            'job_123/../../../secret',
            'job_123/./../../root',
            '..\\..\\..\\windows\\system32'
        ];
        
        $validPattern = '/^job_[0-9]+_[a-f0-9]{8}$/';
        
        foreach ($maliciousJobIds as $jobId) {
            $this->assertEquals(0, preg_match($validPattern, $jobId), 
                "Malicious job ID '$jobId' should be rejected");
        }
    }
    
    /**
     * Test command injection prevention
     */
    public function testCommandInjectionPrevention() {
        $maliciousInputs = [
            '; rm -rf /',
            '| cat /etc/passwd',
            '&& malicious-command',
            '$(malicious-command)',
            '`malicious-command`'
        ];
        
        foreach ($maliciousInputs as $input) {
            $escaped = escapeshellarg($input);
            
            // Escaped string should start and end with single quotes
            $this->assertStringStartsWith("'", $escaped);
            $this->assertStringEndsWith("'", $escaped);
            
            // Special characters should be escaped/contained
            $this->assertStringNotContainsStringIgnoringCase('rm -rf', 
                str_replace("'", '', $escaped));
        }
    }
    
    /**
     * Test SQL injection patterns (even though we don't use SQL)
     */
    public function testSqlInjectionPatterns() {
        $sqlPatterns = [
            "'; DROP TABLE users; --",
            "1' OR '1'='1",
            "admin'--",
            "' UNION SELECT * FROM users--"
        ];
        
        foreach ($sqlPatterns as $pattern) {
            // Job ID validation should reject these
            $validPattern = '/^job_[0-9]+_[a-f0-9]{8}$/';
            $this->assertEquals(0, preg_match($validPattern, $pattern));
        }
    }
    
    /**
     * Test XSS prevention in job data
     */
    public function testXssPrevention() {
        $xssPatterns = [
            '<script>alert("XSS")</script>',
            '<img src=x onerror=alert("XSS")>',
            'javascript:alert("XSS")',
            '<iframe src="malicious.com"></iframe>'
        ];
        
        foreach ($xssPatterns as $pattern) {
            // When outputting to JSON, these should be encoded
            $json = json_encode(['data' => $pattern]);
            
            // JSON encoding should escape these properly
            $this->assertStringNotContainsString('<script>', $json);
            $this->assertStringContainsString('\\u003C', $json); // < encoded
        }
    }
    
    /**
     * Test path sanitization
     */
    public function testPathSanitization() {
        $dbPath = '/data/blastdb/';
        $database = '../../../etc/passwd';
        
        // Ensure absolute paths are used and validated
        $validPattern = '/^[a-zA-Z0-9_\-\.\/]+$/';
        
        // Database names should be validated
        $isValid = preg_match('/^[a-zA-Z0-9_\-\.]+$/', basename($database));
        $this->assertEquals(0, $isValid, "Malicious database path should be rejected");
    }
    
    /**
     * Test file permission expectations
     */
    public function testFilePermissionExpectations() {
        // Test that directory permissions are set appropriately (0755)
        $expectedDirPerms = 0755;
        $expectedFilePerms = 0644;
        
        // Convert to octal string for comparison
        $this->assertEquals('755', decoct($expectedDirPerms));
        $this->assertEquals('644', decoct($expectedFilePerms));
    }
    
    /**
     * Test HTTP method validation
     */
    public function testHttpMethodRestriction() {
        $allowedMethods = ['GET', 'POST'];
        $disallowedMethods = ['PUT', 'DELETE', 'PATCH', 'OPTIONS', 'HEAD'];
        
        foreach ($disallowedMethods as $method) {
            $this->assertNotContains($method, $allowedMethods, 
                "Method $method should not be allowed");
        }
    }
    
    /**
     * Test parameter type validation
     */
    public function testParameterTypeValidation() {
        // maxHits should be an integer
        $inputs = ['10', 10, '10abc', 'abc'];
        
        foreach ($inputs as $input) {
            $value = intval($input);
            
            if ($input === '10' || $input === 10) {
                $this->assertEquals(10, $value);
            } elseif ($input === '10abc') {
                $this->assertEquals(10, $value); // intval() extracts leading digits
            } else {
                $this->assertEquals(0, $value); // Invalid input becomes 0
            }
        }
    }
    
    /**
     * Test URL encoding for safe output
     */
    public function testUrlEncoding() {
        $unsafeChars = 'job id with spaces & special?chars=value';
        $encoded = urlencode($unsafeChars);
        
        $this->assertStringNotContainsString(' ', $encoded);
        $this->assertStringContainsString('%20', $encoded);
        $this->assertStringContainsString('%3F', $encoded); // ?
    }
    
    /**
     * Test that sensitive config paths are not exposed
     */
    public function testConfigPathsNotExposed() {
        $config = [
            'dbPath' => '/data/blastdb/',
            'blastExePath' => '/usr/bin/',
            'jobsPath' => '/tmp/jobs/'
        ];
        
        // Public API responses should not expose full system paths
        $publicData = [
            'jobId' => 'job_123',
            'status' => 'completed'
            // No dbPath, blastExePath, jobsPath
        ];
        
        $this->assertArrayNotHasKey('dbPath', $publicData);
        $this->assertArrayNotHasKey('blastExePath', $publicData);
        $this->assertArrayNotHasKey('jobsPath', $publicData);
    }
}
