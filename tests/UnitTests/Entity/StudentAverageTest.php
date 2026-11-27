<?php

namespace App\Tests\UnitTests\Entity;

use App\Entity\Evaluation;
use App\Entity\Grade;
use App\Entity\Student;
use PHPUnit\Framework\TestCase;

class StudentAverageTest extends TestCase
{
    public function testGetAverageIsNormalizedTo20(): void
    {
        $student = new Student();

        $evaluation1 = new Evaluation();
        $evaluation1->setBareme(20);

        $evaluation2 = new Evaluation();
        $evaluation2->setBareme(40);

        $grade1 = new Grade();
        $grade1->setGrade('15');
        $grade1->setEvaluation($evaluation1);
        $student->addGrade($grade1);

        $grade2 = new Grade();
        $grade2->setGrade('20');
        $grade2->setEvaluation($evaluation2);
        $student->addGrade($grade2);

        // (15/20)*20 = 15 et (20/40)*20 = 10 => (15 + 10) / 2 = 12.5
        $this->assertEqualsWithDelta(12.5, $student->getAverage(), 0.0001);
    }

    public function testGetAverageReturnsNullWhenNoGrades(): void
    {
        $student = new Student();

        $this->assertNull($student->getAverage());
    }
}
