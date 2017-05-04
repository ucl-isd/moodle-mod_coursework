Feature: warnings when settings are not right

    As a manager
    I want to know when there are issues with the setup of the coursework instance
    So that I can take corrective action before stuff goes wrong

    Background:
        Given there is a course
        And there is a coursework

    Scenario: managers see a warning about there being too few teachers
        Given there is a teacher
        And the coursework "numberofmarkers" setting is "3" in the database
        And I am logged in as a manager
        When I visit the coursework page
        Then I should see "There are only"

    Scenario: Teachers do not see the warnign about too few teachers
        Given there is a teacher
        And the coursework "numberofmarkers" setting is "3" in the database
        And I am logged in as a teacher
        When I visit the coursework page
        Then I should not see "There are only"

    Scenario: There is no warning when there are enough teachers
        Given there is a teacher
        And the coursework "numberofmarkers" setting is "1" in the database
        And I am logged in as a manager
        When I visit the coursework page
        Then I should not see "There are only"
