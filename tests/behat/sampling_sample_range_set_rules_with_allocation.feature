@mod @mod_coursework @mod_coursework_sampling @javascript
Feature: Automatic sample based on range set grades using marking of students in stage 1 and 2 with allocation

  As a manager, I want to be able to automatically allocate assessors to students
  using a set of grade rules with upper and lower limits

  I want to be sure that Submissions are not in draft when being sampled, they must be finalised to be sampled.

  Background:
    Given the following "course" exists:
      | fullname  | Course 1 |
      | shortname | C1       |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | manager   | manager1 | manager1@example.com |
      | teacher1 | teacher   | teacher1 | teacher1@example.com |
      | teacher2 | teacher   | teacher2 | teacher2@example.com |
      | teacher3 | teacher   | teacher3 | teacher3@example.com |
      | student1 | student   | student1 | student1@example.com |
      | student2 | student   | student2 | student2@example.com |
      | student3 | student   | student3 | student3@example.com |
      | student4 | student   | student4 | student4@example.com |
      | student5 | student   | student5 | student5@example.com |
      | student6 | student   | student6 | student6@example.com |
      | student7 | student   | student7 | student7@example.com |
      | student8 | student   | student8 | student8@example.com |
      | student9 | student   | student9 | student9@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | manager1 | C1     | manager |
      | teacher1 | C1     | teacher |
      | teacher2 | C1     | teacher |
      | teacher3 | C1     | teacher |
      | student1 | C1     | student |
      | student2 | C1     | student |
      | student3 | C1     | student |
      | student4 | C1     | student |
      | student5 | C1     | student |
      | student6 | C1     | student |
      | student7 | C1     | student |
      | student8 | C1     | student |
      | student9 | C1     | student |
    And the following "activity" exists:
      | activity                    | coursework   |
      | course                      | C1           |
      | name                        | Coursework   |
      | deadline                    | ##tomorrow## |
      | numberofmarkers             | 3            |
      | allocationenabled           | 1            |
      | assessorallocationstrategy  | equal        |
      | samplingenabled             | 1            |
      | automaticagreementstrategy  | none         |
    And the following "mod_coursework > submissions" exist:
      | allocatable | coursework | finalisedstatus |
      | student1    | Coursework | 0               |
      | student2    | Coursework | 0               |
      | student3    | Coursework | 1               |
      | student4    | Coursework | 1               |
      | student5    | Coursework | 1               |
      | student6    | Coursework | 1               |
      | student7    | Coursework | 1               |
      | student8    | Coursework | 1               |
    And the following "mod_coursework > feedbacks" exist:
      | allocatable | coursework | assessor | stageidentifier | grade | feedbackcomment  | isfinalgrade | finalised |
      | student1    | Coursework | teacher1 | assessor_1      | 12    | New comment here | 0            | 0         |
      | student2    | Coursework | teacher2 | assessor_1      | 43    | New comment here | 0            | 0         |
      | student3    | Coursework | teacher3 | assessor_1      | 45    | New comment here | 0            | 1         |
      | student4    | Coursework | teacher1 | assessor_1      | 59    | New comment here | 0            | 1         |
      | student5    | Coursework | teacher2 | assessor_1      | 80    | New comment here | 0            | 1         |
      | student6    | Coursework | teacher3 | assessor_1      | 80    | New comment here | 0            | 1         |
      | student7    | Coursework | teacher1 | assessor_1      | 80    | New comment here | 0            | 1         |
    And the following "activity" exists:
      | activity                    | coursework    |
      | course                      | C1            |
      | name                        | Coursework2   |
      | deadline                    | ##yesterday## |
      | numberofmarkers             | 3             |
      | allocationenabled           | 1             |
      | assessorallocationstrategy  | equal         |
      | samplingenabled             | 1             |
      | automaticagreementstrategy  | none          |
    And the following "mod_coursework > submissions" exist:
      | allocatable | coursework  | finalisedstatus |
      | student1    | Coursework2 | 0               |
      | student2    | Coursework2 | 0               |
      | student3    | Coursework2 | 1               |
    And the following "mod_coursework > feedbacks" exist:
      | allocatable | coursework  | assessor | stageidentifier | grade | feedbackcomment  | isfinalgrade | finalised |
      | student1    | Coursework2 | teacher1 | assessor_1      | 12    | New comment here | 0            | 0         |
      | student2    | Coursework2 | teacher2 | assessor_1      | 43    | New comment here | 0            | 0         |
      | student3    | Coursework2 | teacher3 | assessor_1      | 45    | New comment here | 0            | 1         |
    And the following "activity" exists:
      | activity                    | coursework    |
      | course                      | C1            |
      | name                        | Coursework3   |
      | deadline                    | ##tomorrow##  |
      | numberofmarkers             | 3             |
      | allocationenabled           | 0             |
      | assessorallocationstrategy  | none          |
      | samplingenabled             | 1             |
      | automaticagreementstrategy  | none          |
    And the following "mod_coursework > submissions" exist:
      | allocatable | coursework  | finalisedstatus |
      | student1    | Coursework3 | 0               |
      | student2    | Coursework3 | 1               |
      | student3    | Coursework3 | 1               |
    And the following "mod_coursework > allocations" exist:
      | allocatable | coursework  | assessor | stageidentifier |
      | student1    | Coursework3 | teacher1 | assessor_1      |
      | student2    | Coursework3 | teacher2 | assessor_1      |
      | student3    | Coursework3 | teacher3 | assessor_1      |
    And the following "mod_coursework > feedbacks" exist:
      | allocatable | coursework  | assessor | stageidentifier | grade | feedbackcomment  | isfinalgrade | finalised |
      | student1    | Coursework3 | teacher1 | assessor_1      | 12    | New comment here | 0            | 0         |
      | student2    | Coursework3 | teacher2 | assessor_1      | 43    | New comment here | 0            | 0         |
      | student3    | Coursework3 | teacher3 | assessor_1      | 45    | New comment here | 0            | 1         |

  Scenario: Applying sampling of finalised and non finalised feedback, then applying automatic equal allocation
    Given I am on the "Coursework" "coursework activity" page logged in as "manager1"
    When I navigate to "Allocate markers" in current page administration
    And I set the following fields to these values:
      | assessor_2_samplingstrategy | Automatic |
      | assessor_2_samplerules_0    | 1         |
      | assessor_2_sampletype_0     | grade     |
      | assessor_2_samplefrom_0     | 0         |
      | assessor_2_sampleto_0       | 45        |
    And I click on "Add rule" "link"
    And I set the following fields to these values:
      | assessor_2_samplingstrategy | Automatic |
      | assessor_2_samplerules_1    | 1         |
      | assessor_2_sampletype_1     | grade     |
      | assessor_2_samplefrom_1     | 49        |
      | assessor_2_sampleto_1       | 49        |
    And I click on "Add rule" "link"
    And I set the following fields to these values:
      | assessor_2_samplingstrategy | Automatic |
      | assessor_2_samplerules_2    | 1         |
      | assessor_2_sampletype_2     | grade     |
      | assessor_2_samplefrom_2     | 59        |
      | assessor_2_sampleto_2       | 59        |
    And I press "save_sampling"
    Then "student student1" row "Marker 2" column of "mod_coursework_allocatemarkers" table should not contain "Automatically included in sample"
    And "student student2" row "Marker 2" column of "mod_coursework_allocatemarkers" table should not contain "Automatically included in sample"
    And "student student3" row "Marker 2" column of "mod_coursework_allocatemarkers" table should contain "Automatically included in sample"
    And "student student4" row "Marker 2" column of "mod_coursework_allocatemarkers" table should contain "Automatically included in sample"
    And "student student5" row "Marker 2" column of "mod_coursework_allocatemarkers" table should not contain "Automatically included in sample"
    And "student student6" row "Marker 2" column of "mod_coursework_allocatemarkers" table should not contain "Automatically included in sample"
    And "student student7" row "Marker 2" column of "mod_coursework_allocatemarkers" table should not contain "Automatically included in sample"
    And "student student8" row "Marker 2" column of "mod_coursework_allocatemarkers" table should not contain "Automatically included in sample"

    # Apply populates some loading text, and the returning AJAX on success invokes location.reload(true)
    # This invalidates any strategy checking for that loading text due to race conditions, except creating custom step definitions sniffing window.location
    # The returning AJAX on success toggles the my_overlay then invokes location.reload(true)
    When I press "Apply"
    And I wait until "#coursework_input_buttons #countdown.my_overlay" "css_element" does not exist
    And I wait until the page is ready
    Then "student student3" row "Marker 2" column of "mod_coursework_allocatemarkers" table should contain "teacher1"
    And "student student4" row "Marker 2" column of "mod_coursework_allocatemarkers" table should contain "teacher2"

    When I am on the "Coursework" "coursework activity" page
    Then I should see "teacher teacher1" in the "student student3" "table_row"
    And I should see "teacher teacher2" in the "student student4" "table_row"

  Scenario: Applying sampling of finalised and non finalised feedback with the deadline pastdue, then applying automatic equal allocation
    Given I am on the "Coursework2" "coursework activity" page logged in as "manager1"
    When I navigate to "Allocate markers" in current page administration
    And I set the following fields to these values:
      | assessor_2_samplingstrategy | Automatic |
      | assessor_2_samplerules_0    | 1         |
      | assessor_2_sampletype_0     | grade     |
      | assessor_2_samplefrom_0     | 0         |
      | assessor_2_sampleto_0       | 45        |
    And I click on "Add rule" "link"
    And I set the following fields to these values:
      | assessor_2_samplingstrategy | Automatic |
      | assessor_2_samplerules_1    | 1         |
      | assessor_2_sampletype_1     | grade     |
      | assessor_2_samplefrom_1     | 49        |
      | assessor_2_sampleto_1       | 49        |
    And I click on "Add rule" "link"
    And I set the following fields to these values:
      | assessor_2_samplingstrategy | Automatic |
      | assessor_2_samplerules_2    | 1         |
      | assessor_2_sampletype_2     | grade     |
      | assessor_2_samplefrom_2     | 59        |
      | assessor_2_sampleto_2       | 59        |
    And I press "save_sampling"
    Then "student student1" row "Marker 2" column of "mod_coursework_allocatemarkers" table should contain "Automatically included in sample"
    And "student student2" row "Marker 2" column of "mod_coursework_allocatemarkers" table should contain "Automatically included in sample"
    And "student student3" row "Marker 2" column of "mod_coursework_allocatemarkers" table should contain "Automatically included in sample"
    And I should see "Please make sure markers are allocated."

    # Apply populates some loading text, and the returning AJAX on success invokes location.reload(true)
    # This invalidates any strategy checking for that loading text due to race conditions, except creating custom step definitions sniffing window.location
    # The returning AJAX on success toggles the my_overlay then invokes location.reload(true)
    When I press "Apply"
    And I wait until "#coursework_input_buttons #countdown.my_overlay" "css_element" does not exist
    And I wait until the page is ready
    Then "student student1" row "Marker 2" column of "mod_coursework_allocatemarkers" table should contain "Automatically included in sample"
    And "student student2" row "Marker 2" column of "mod_coursework_allocatemarkers" table should contain "Automatically included in sample"
    And "student student3" row "Marker 2" column of "mod_coursework_allocatemarkers" table should contain "Automatically included in sample"
    And "student student1" row "Marker 2" column of "mod_coursework_allocatemarkers" table should contain "teacher2"
    And "student student2" row "Marker 2" column of "mod_coursework_allocatemarkers" table should contain "teacher1"
    And "student student3" row "Marker 2" column of "mod_coursework_allocatemarkers" table should contain "teacher3"

    When I am on the "Coursework2" "coursework activity" page
    Then I should see "teacher teacher1" in the "student student1" "table_row"
    And I should see "teacher teacher2" in the "student student2" "table_row"
    And I should see "teacher teacher3" in the "student student3" "table_row"

  Scenario: Applying sampling of finalised and non finalised feedback with manual allocations, then applying automatic equal allocation
    Given I am on the "Coursework3" "coursework activity" page logged in as "manager1"
    When I navigate to "Allocate markers" in current page administration
    And I set the following fields to these values:
      | assessor_2_samplingstrategy | Automatic |
      | assessor_2_samplerules_0    | 1         |
      | assessor_2_sampletype_0     | grade     |
      | assessor_2_samplefrom_0     | 0         |
      | assessor_2_sampleto_0       | 45        |
    And I click on "Add rule" "link"
    And I set the following fields to these values:
      | assessor_2_samplingstrategy | Automatic |
      | assessor_2_samplerules_1    | 1         |
      | assessor_2_sampletype_1     | grade     |
      | assessor_2_samplefrom_1     | 49        |
      | assessor_2_sampleto_1       | 49        |
    And I click on "Add rule" "link"
    And I set the following fields to these values:
      | assessor_2_samplingstrategy | Automatic |
      | assessor_2_samplerules_2    | 1         |
      | assessor_2_sampletype_2     | grade     |
      | assessor_2_samplefrom_2     | 59        |
      | assessor_2_sampleto_2       | 59        |
    And I press "save_sampling"
    Then "student student1" row "Marker 2" column of "mod_coursework_allocatemarkers" table should not contain "Automatically included in sample"
    And "student student2" row "Marker 2" column of "mod_coursework_allocatemarkers" table should contain "Automatically included in sample"
    And "student student3" row "Marker 2" column of "mod_coursework_allocatemarkers" table should contain "Automatically included in sample"

    When I navigate to "Settings" in current page administration
    And I expand all fieldsets
    And I set the field "allocationenabled" to "1"
    And I set the field "assessorallocationstrategy" to "Automatic (even distribution)"
    And I press "Save and display"

    And I navigate to "Allocate markers" in current page administration
    And I press "save_sampling"
    Then "student student1" row "Marker 2" column of "mod_coursework_allocatemarkers" table should not contain "Automatically included in sample"
    And "student student2" row "Marker 2" column of "mod_coursework_allocatemarkers" table should contain "Automatically included in sample"
    And "student student3" row "Marker 2" column of "mod_coursework_allocatemarkers" table should contain "Automatically included in sample"

    # Apply populates some loading text, and the returning AJAX on success invokes location.reload(true)
    # This invalidates any strategy checking for that loading text due to race conditions, except creating custom step definitions sniffing window.location
    # The returning AJAX on success toggles the my_overlay then invokes location.reload(true)
    When I press "Apply"
    And I wait until "#coursework_input_buttons #countdown.my_overlay" "css_element" does not exist
    And I wait until the page is ready
    Then "student student1" row "Marker 2" column of "mod_coursework_allocatemarkers" table should not contain "Automatically included in sample"
    And "student student2" row "Marker 2" column of "mod_coursework_allocatemarkers" table should contain "Automatically included in sample"
    And "student student3" row "Marker 2" column of "mod_coursework_allocatemarkers" table should contain "Automatically included in sample"

    When I am on the "Coursework3" "coursework activity" page
    Then I should see "teacher teacher1" in the "student student1" "table_row"
    And I should not see "teacher teacher2" in the "student student1" "table_row"
    And I should not see "teacher teacher3" in the "student student1" "table_row"
    And I should see "teacher teacher2" in the "student student2" "table_row"
    And I should see "teacher teacher1" in the "student student2" "table_row"
    And I should see "teacher teacher3" in the "student student3" "table_row"
    And I should see "teacher teacher2" in the "student student3" "table_row"
