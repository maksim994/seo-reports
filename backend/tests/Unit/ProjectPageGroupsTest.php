<?php

namespace Tests\Unit;

use App\Support\ProjectPageGroups;
use Tests\TestCase;

class ProjectPageGroupsTest extends TestCase
{
    public function test_url_path_matches_first_enabled_regex_group(): void
    {
        $groups = ProjectPageGroups::normalize([
            ['label' => 'Инфо раздел', 'pattern' => '^/blog/', 'enabled' => true],
            ['label' => 'Каталог', 'pattern' => '^/catalog/', 'enabled' => true],
        ]);

        $this->assertSame('Инфо раздел', ProjectPageGroups::matchLabel('https://example.com/blog/post-1?utm=1', $groups));
        $this->assertSame('Каталог', ProjectPageGroups::matchLabel('/catalog/item', $groups));
        $this->assertSame('Прочее', ProjectPageGroups::matchLabel('/contacts/', $groups));
    }

    public function test_invalid_regex_is_filtered_from_normalized_groups(): void
    {
        $groups = ProjectPageGroups::normalize([
            ['label' => 'Broken', 'pattern' => '[', 'enabled' => true],
            ['label' => 'Blog', 'pattern' => '^/blog/', 'enabled' => true],
        ]);

        $this->assertCount(1, $groups);
        $this->assertSame('Blog', $groups[0]['label']);
    }
}
