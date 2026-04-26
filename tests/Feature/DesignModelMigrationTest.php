<?php

use App\Models\Design;
use Illuminate\Support\Facades\Schema;

describe('Design model — Phase 2 S3 migration', function () {

    // Task 1: migration drops file_url column
    it('does not have file_url column in designs table', function () {
        expect(Schema::hasColumn('designs', 'file_url'))->toBeFalse();
    });

    it('still has file_path column in designs table', function () {
        expect(Schema::hasColumn('designs', 'file_path'))->toBeTrue();
    });

    // Task 2: Design model does not include file_url in fillable
    it('does not allow mass-assigning file_url', function () {
        $design = Design::factory()->create();

        $design->fill(['file_url' => 'https://evil.example.com/hack.png']);

        expect($design->isDirty('file_url'))->toBeFalse();
    });

    it('allows mass-assigning file_path', function () {
        $design = Design::factory()->create();
        $newPath = 'designs/new-path.png';

        $design->fill(['file_path' => $newPath]);

        expect($design->file_path)->toBe($newPath);
    });

    // Triangulation: model can be created without file_url
    it('can create a Design without providing file_url', function () {
        $design = Design::factory()->create();

        expect($design)->toBeInstanceOf(Design::class)
            ->and($design->file_path)->toBeString()->not->toBeEmpty()
            ->and($design->getAttributes())->not->toHaveKey('file_url');
    });
});
