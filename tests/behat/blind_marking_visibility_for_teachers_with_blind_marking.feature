@mod @mod_coursework
Feature: Visibility for teachers with blind marking

    As a manager
    I want to be able to prevent teachers from seeing each others' marks
    So that I can be sure that they are not influenced by each other and the marking is fair

    Background:
        Given there is a course
        And there is a coursework
        And blind marking is enabled

    Scenario: The student names are hidden from teachers in the user cells
        Given I am logged in as a teacher
        And there is a student
        When I visit the coursework page
        Then I should not see the student's name in the user cell

    Scenario: The user names are hidden from teachers in the group cells
        Given I am logged in as a teacher
        And there is a student
        And group submissions are enabled
        And the student is a member of a group
        And the group is part of a grouping for the coursework
        When I visit the coursework page
        Then I should not see the student's name in the group cell

    Scenario: Teachers cannot see other initial grades before final grading happens
        Given the coursework "numberofmarkers" setting is "2" in the database
        And there is a teacher
        And there is another teacher
        And there is a student
        And the student has a submission
        And the submission is finalised
        And there are feedbacks from both teachers
        And I am logged in as the other teacher
        When I visit the coursework page
        Then I should not see the grade from the teacher in the assessor table



