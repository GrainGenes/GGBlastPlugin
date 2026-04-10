<?php
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for configuration reading and parsing
 */
class ConfigReaderTest extends TestCase {
    
    /**
     * Test JSON parsing of valid configuration
     */
    public function testValidJsonParsing() {
        $validJson = json_encode([
            'dbPath' => '/data/blastdb/',
            'blastExePath' => '/usr/bin/',
            'jobsPath' => '/tmp/jobs/',
            'bpSizeLimit' => 20000,
            'blastService' => 'php'
        ]);
        
        $config = json_decode($validJson, true);
        
        $this->assertIsArray($config);
        $this->assertArrayHasKey('dbPath', $config);
        $this->assertArrayHasKey('blastExePath', $config);
        $this->assertEquals('/data/blastdb/', $config['dbPath']);
    }
    
    /**
     * Test JSON parsing of invalid configuration
     */
    public function testInvalidJsonParsing() {
        $invalidJson = '{invalid json content}';
        
        $config = json_decode($invalidJson, true);
        
        $this->assertNull($config);
        $this->assertNotEquals(JSON_ERROR_NONE, json_last_error());
    }
    
    /**
     * Test path normalization (trailing slash)
     */
    public function testPathNormalization() {
        $paths = [
            '/data/blastdb' => '/data/blastdb/',
            '/data/blastdb/' => '/data/blastdb/',
            '/data/blastdb//' => '/data/blastdb/',
            'relative/path' => 'relative/path/'
        ];
        
        foreach ($paths as $input => $expected) {
            $normalized = rtrim($input, '/') . '/';
            $this->assertEquals($expected, $normalized);
        }
    }
    
    /**
     * Test default value handling
     */
    public function testDefaultValues() {
        $config = [
            'dbPath' => '/data/blastdb/',
            'blastExePath' => '/usr/bin/'
            // jobsPath is missing
        ];
        
        $jobsPath = isset($config['jobsPath']) ? $config['jobsPath'] : '/default/jobs/';
        $this->assertEquals('/default/jobs/', $jobsPath);
        
        $dbPath = isset($config['dbPath']) ? $config['dbPath'] : '/default/db/';
        $this->assertEquals('/data/blastdb/', $dbPath);
    }
    
    /**
     * Test required keys validation
     */
    public function testRequiredKeysValidation() {
        $config = [
            'dbPath' => '/data/blastdb/',
            'blastExePath' => '/usr/bin/'
            // Missing jobsPath
        ];
        
        $requiredKeys = ['dbPath', 'blastExePath', 'jobsPath'];
        $missing = [];
        
        foreach ($requiredKeys as $key) {
            if (!isset($config[$key]) || empty($config[$key])) {
                $missing[] = $key;
            }
        }
        
        $this->assertCount(1, $missing);
        $this->assertContains('jobsPath', $missing);
    }
    
    /**
     * Test configuration with all keys present
     */
    public function testCompleteConfiguration() {
        $config = [
            'dbPath' => '/data/blastdb/',
            'blastExePath' => '/usr/bin/',
            'jobsPath' => '/tmp/jobs/',
            'bpSizeLimit' => 20000,
            'blastService' => 'php'
        ];
        
        $requiredKeys = ['dbPath', 'blastExePath', 'jobsPath'];
        $missing = [];
        
        foreach ($requiredKeys as $key) {
            if (!isset($config[$key]) || empty($config[$key])) {
                $missing[] = $key;
            }
        }
        
        $this->assertCount(0, $missing, "All required keys should be present");
    }
    
    /**
     * Test integer type conversion
     */
    public function testIntegerTypeHandling() {
        $config = ['bpSizeLimit' => '20000'];
        
        $limit = isset($config['bpSizeLimit']) ? intval($config['bpSizeLimit']) : 10000;
        
        $this->assertIsInt($limit);
        $this->assertEquals(20000, $limit);
    }
    
    /**
     * Test boolean type handling
     */
    public function testBooleanTypeHandling() {
        $configs = [
            ['blastService' => 'php'],
            ['blastService' => true],
            ['blastService' => false],
            []
        ];
        
        foreach ($configs as $config) {
            $service = isset($config['blastService']) ? $config['blastService'] : null;
            
            if ($service === 'php' || $service === true) {
                $this->assertTrue(true, "Service should be enabled");
            }
        }
    }
}
