<?php

namespace Tests\Unit;

use App\Models\User;
use Tests\TestCase;

class UserInstitutionTest extends TestCase
{
    public function test_it_maps_unilab_locations_to_the_unilab_institution(): void
    {
        $this->assertEquals(User::INSTITUTION_UNILAB, User::institutionForLocation(User::LOCATION_CAMPUS));
        $this->assertEquals(User::INSTITUTION_UNILAB, User::institutionForLocation(User::LOCATION_CENTRO));
    }

    public function test_it_maps_cre_locations_to_the_cre_institution(): void
    {
        $this->assertEquals(User::INSTITUTION_CRE, User::institutionForLocation(User::LOCATION_CRE_IJUI));
        $this->assertEquals(User::INSTITUTION_CRE, User::institutionForLocation(User::LOCATION_CRE_SANTA_ROSA));
        $this->assertEquals(User::INSTITUTION_CRE, User::institutionForLocation(User::LOCATION_CRE_PANAMBI));
        $this->assertEquals(User::INSTITUTION_CRE, User::institutionForLocation(User::LOCATION_CRE_TRES_PASSOS));
    }

    public function test_institution_display_name_is_correct_for_each_institution(): void
    {
        $this->assertEquals('UniLab', User::institutionDisplayName(User::INSTITUTION_UNILAB));
        $this->assertEquals('CRE', User::institutionDisplayName(User::INSTITUTION_CRE));
    }
}
