@mod @mod_coursework
Feature: Installing the coursework module and making sure it works

    In order to start using the Coursework module
    As an admin
    I need to be able to successfully install the module in a course and add an instance

    @javascript
    Scenario: I can add a new instance of the coursework module to a course
        Given there is a course
        And I am logged in as an editing teacher
        And I visit the course page
        And I turn editing mode on
        When I add a "Coursework" to section "3" and I fill the form with:
            | name                            | Test coursework             |
            | Description                     | Test coursework description |
        Then I should be on the course page

    Scenario: The module can be used with course completion enabled
        Given there is a course
        And I am logged in as an editing teacher
        And the course has completion enabled
        When I visit the course page
        And I turn editing mode on
        When I add a "Coursework" to section "3" and I fill the form with:
            | name        | Test coursework             |
            | Description | Test coursework description |
        Then I should be on the course page



