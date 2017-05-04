@mod @mod_coursework
Feature: Testing that the factories for behat steps work. If any tests fail, fix this FIRST.

    As a developer maintaining the coursework module
    I want to be able to use a factory to generate the scenario context
    So that my tests are easier to write and run faster

    @javascript
    Scenario: Making a coursework
        Given there is a course
        And I am logged in as a teacher
        And there is a coursework
        When I visit the coursework page
        Then I should see the title of the coursework on the page

    Scenario: the submission factory works properly and shows the file on the page
        Given there is a course
        And there is a coursework
        And I am logged in as a student
        And I have a submission
        When I visit the coursework page
        Then I should see the file on the page

    @javascript
    Scenario: the submission factory works properly and shows the file in the upload area
        Given there is a course
        And there is a coursework
        And I am logged in as a student
        And I have a submission
        When I visit the coursework page
        And I click on the edit submission button
        Then I should see "1" elements in "Upload a file" filemanager

    Scenario: Making a coursework sets the defaults correctly
        Given there is a course
        And I am logged in as an editing teacher
        When I visit the course page
        And I turn editing mode on
        When I add a "Coursework" to section "3" and I fill the form with:
            | name         | Test coursework             |
            | Description  | Test coursework description |
        Then the coursework general feedback should be disabled

    Scenario: The coursework settings can be changed
        Given there is a course
        And I am logged in as an editing teacher
        And there is a coursework
        And the coursework "blindmarking" setting is "1" in the database
        When I visit the coursework settings page
        Then the field "blindmarking" matches value "1"

    Scenario: disabling general feedback alters the db setting (checkboxes bug is fixed - 0 was being interpreted as 1)
        Given there is a course
        And I am logged in as an editing teacher
        When I visit the course page
        And I turn editing mode on
        When I add a "Coursework" to section "3" and I fill the form with:
            | name                  | Test coursework             |
            | Description           | Test coursework description |
            | blindmarking          | 0                           |
        Then the coursework "blindmarking" setting should be "0" in the database

    Scenario: logged in as a teacher works
        Given there is a course
        And I am logged in as a teacher
        When I visit the course page
        Then I should be on the course page

    Scenario: logged in as a manager works
        Given there is a course
        And I am logged in as a manager
        When I visit the course page
        Then I should be on the course page

    Scenario: logged in as a manager works when a student has been created
        Given there is a course
        And there is a student
        Then I am logged in as a manager

    Scenario: Making a setting NULL
        Given there is a course
        And there is a coursework
        And the coursework "individualfeedback" setting is "NULL" in the database
        Then the coursework "blindmarking" setting should be "0" in the database



