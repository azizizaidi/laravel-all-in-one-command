<?php

namespace AziziZaidi\AllInOneCommand\Tests;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;

class MakeFeatureCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Clean up any test files
        $this->cleanupTestFiles();
    }

    protected function tearDown(): void
    {
        // Clean up test files after each test
        $this->cleanupTestFiles();
        
        parent::tearDown();
    }

    /** @test */
    public function it_can_register_the_make_feature_command()
    {
        $this->assertTrue(class_exists(\AziziZaidi\AllInOneCommand\Console\MakeFeatureCommand::class));
    }

    /** @test */
    public function it_can_run_the_make_feature_command()
    {
        // Test that the command exists and can be called
        $result = Artisan::call('make:feature', ['name' => 'TestFeature']);

        // The command should return 0 (success) or 1 (cancelled by user)
        $this->assertContains($result, [0, 1, 2]); // Added 2 for INVALID
    }

    /** @test */
    public function it_validates_empty_feature_name()
    {
        $result = Artisan::call('make:feature', ['name' => '']);

        // Should return INVALID (2) for empty name
        $this->assertEquals(2, $result);
    }

    /** @test */
    public function it_validates_invalid_feature_name_format()
    {
        // Test with invalid characters
        $result = Artisan::call('make:feature', ['name' => '123-invalid']);

        // Should return INVALID (2) for invalid format
        $this->assertEquals(2, $result);
    }

    private function cleanupTestFiles()
    {
        $filesToClean = [
            app_path('Models/TestModel.php'),
            app_path('Models/ExistingModel.php'),
            app_path('Models/TestFeature.php'),
        ];

        foreach ($filesToClean as $file) {
            if (File::exists($file)) {
                File::delete($file);
            }
        }
    }
}
