<?php

namespace App\Tests\UnitTests\Entity;

use App\Entity\Evaluation;
use App\Entity\Grade;
use App\Entity\Student;
use PHPUnit\Framework\TestCase;

class EvaluationTest extends TestCase
{
    public function testGetAverageReturnsMeanOfGrades(): void
    {
        $evaluation = new Evaluation();
        $evaluation->setBareme(20);

        $student = $this->createMock(Student::class);

        $grade1 = new Grade();
        $grade1->setGrade('12');
        $grade1->setStudent($student);
        $evaluation->addGrade($grade1);

        $grade2 = new Grade();
        $grade2->setGrade('16');
        $grade2->setStudent($student);
        $evaluation->addGrade($grade2);

        $this->assertEqualsWithDelta(14.0, $evaluation->getAverage(), 0.0001);
    }

    public function testGetAverageIgnoresUngradedStudents(): void
    {
        $evaluation = new Evaluation();
        $evaluation->setBareme(20);

        $student = $this->createMock(Student::class);

        $grade1 = new Grade();
        $grade1->setGrade('10');
        $grade1->setStudent($student);
        $evaluation->addGrade($grade1);

        // Élève absent : note non saisie, ignorée dans la moyenne
        $grade2 = new Grade();
        $grade2->setGrade(null);
        $grade2->setStudent($student);
        $evaluation->addGrade($grade2);

        $this->assertEqualsWithDelta(10.0, $evaluation->getAverage(), 0.0001);
    }

    public function testGetAverageReturnsNullWhenNoGrades(): void
    {
        $evaluation = new Evaluation();

        $this->assertNull($evaluation->getAverage());
    }
}
