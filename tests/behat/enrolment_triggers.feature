@mod @mod_coursework
Feature: the students should be auto allocated on enrolment

    As a manager
    I want the students to be reallocated when they or their assessors are added or removed from the course
    So that they always have a configured allocation for an appropriate assessor

    Background:
        Given there is a course
        And there is a coursework
        And there is a teacher
        And the coursework "allocationenabled" setting is "1" in the database
        And there are no allocations in the db

    Scenario: new students should be allocated when they join
        Given there is a student
        When I visit the allocations page
        Then the student should be allocated to an assessor

    Scenario: exisitng manual allocations should not be reallocated when a tutor joins
        pending

    Scenario: existing auto allocations should not be reallocated when a tutor joins
        pending

    Scenario: exisitng graded allocations should not be reallocated when a tutor joins
        pending

    Scenario: exisitng manual allocations should not be reallocated when a tutor leaves
        pending

    Scenario: existing auto allocations should not be reallocated when a tutor leaves
        pending

    Scenario: exisitng graded allocations should not be reallocated when a tutor leaves
        pending

