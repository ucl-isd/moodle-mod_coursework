@mod @mod_coursework @javascript
Feature: For the final grade the mark should be to the decimal point

  Background:
    Given there is a course
    And there is a coursework
    And the coursework "numberofmarkers" setting is "2" in the database
    And there is a teacher
    And there is another teacher
    And there is a student
    And the student has a submission
    And the submission is finalised

  Scenario: Automatic agreement of grades = "average grade" should use decimal places
    Given the coursework "automaticagreementstrategy" setting is "average_grade" in the database
    And I am logged in as a teacher
    And I visit the coursework page
    And I click on "Add feedback" "link"
    And I set the field "Grade" to "59"
    And I press "Save and finalise"
    And I log out
    And I log in as the other teacher
    And I visit the coursework page
    And I click on "Add feedback" "link"
    And I set the field "Grade" to "58"
    And I press "Save and finalise"
    And I visit the coursework page
    Then I should see the final agreed grade as 58.5 on the page

  Scenario: A manager can enter decimals for the final grade and I can update grade when entered with illegal values being rejected
    Given I am logged in as a teacher
    And I visit the coursework page
    And I click on "Add feedback" "link"
    And I set the field "Grade" to "59"
    And I press "Save and finalise"
    And I log out
    And I log in as the other teacher
    And I visit the coursework page
    And I click on "Add feedback" "link"
    And I set the field "Grade" to "58"
    And I press "Save and finalise"
    And I log out
    And I log in as a manager
    And I visit the coursework page
    And I click on "Add agreed feedback" "link"
    And I wait until the page is ready
    And I set the field "Grade" to "56.12"
    And I press "Save and finalise"
    And I visit the coursework page
    Then I should see the final agreed grade as 56.12 on the page
    And I click on "56.12" "link"
    And I set the field "Grade" to "56.99"
    And I press "Save and finalise"
    And I visit the coursework page
    Then I should see the final agreed grade as 56.99 on the page
    And I click on "56.99" "link"
    And I set the field "Grade" to "-2"
    And I press "Save and finalise"
    And I wait until the page is ready
    Then I should see "You must enter a whole number or decimal within the range of marks accepted by this coursework"
    And I set the field "Grade" to "Illegal text"
    And I press "Save and finalise"
    Then I should see "You must enter a whole number or decimal within the range of marks accepted by this coursework"
    And I set the field "Grade" to "57.01"
    And I press "Save and finalise"
    Then I should see the final agreed grade as 57.01 on the page

  Scenario: If I enter non-numeric characters for the grade I should see an error
    Given there are feedbacks from both teachers
    And I log in as a manager
    And I visit the coursework page
    And I click on "Add agreed feedback" "link"
    And I wait until the page is ready
    And I set the field "Grade" to "abc"
    And I wait "1" seconds
    Then I should see "You must enter a whole number or decimal within the range of marks accepted by this coursework"

  Scenario: If I enter a grade which is out of range I should see an error
    Given there are feedbacks from both teachers
    And I log in as a manager
    And I visit the coursework page
    And I click on "Add agreed feedback" "link"
    And I wait until the page is ready
    And I set the field "Grade" to "1234"
    And I press "Save and finalise"
    And I wait until the page is ready
    Then I should see "You must enter a whole number or decimal within the range of marks accepted by this coursework"