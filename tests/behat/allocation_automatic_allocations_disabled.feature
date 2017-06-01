@mod @mod_coursework
Feature: Automatic allocations can be disabled

    As a manager
    I want to be able to turn off automatic allocations
    So that I can choose the teachers manually and not have weird or inappropriate allocations

    Background:
        Given the managers are not allowed to grade
        And there is a course
        And there is a coursework
        And there is a teacher
        And I am logged in as a manager
        And the managers are not allowed to grade
        And there are no allocations in the db

    Scenario: Nothing happens with
        Given the coursework "allocationenabled" setting is "1" in the database
        And the coursework "assessorallocationstrategy" setting is "none" in the database
        When there is a student
        Then there should be no allocations in the db
