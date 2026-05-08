@mod @mod_coursework @mod_coursework_sampling @javascript
Feature: Automatic sample based on range set grades using marking of students in stage 1 and 2

  As a manager, I want to be able to automatically allocate assessors to students
  using a set of grade rules with upper and lower limits
  for a large group of students so that the marking is fairly distributed
  so they mark more evenly and randomly.

  Background:
    Given the following "course" exists:
      | fullname  | Course 1 |
      | shortname | C1       |
    And the following "activity" exists:
      | activity        | coursework    |
      | course          | C1            |
      | name            | Coursework    |
      | deadline        | ##yesterday## |
      | numberofmarkers | 3             |
      | samplingenabled | 1             |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | manager   | manager1 | manager1@example.com |
      | teacher1 | teacher   | teacher1 | teacher1@example.com |
      | student1 | student   | student1 | student1@example.com |
      | student2 | student   | student2 | student2@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | manager1 | C1     | manager |
      | teacher1 | C1     | teacher |
      | student1 | C1     | student |
      | student2 | C1     | student |
    And the following "mod_coursework > submissions" exist:
      | allocatable | coursework | finalisedstatus |
      | student1    | Coursework | 1               |
      | student2    | Coursework | 1               |
    And the following "mod_coursework > feedbacks" exist:
      | allocatable | coursework | assessor | stageidentifier | grade | feedbackcomment  |
      | student1    | Coursework | teacher1 | assessor_1      | 56    | New comment here |
      | student2    | Coursework | teacher1 | assessor_1      | 45    | New comment here |

  @javascript
  Scenario: Automatically allocating a set of students within specified grade rule range in stage 2 based on stage 1 grades
    Given I am on the "Coursework" "coursework activity" page logged in as "manager1"
    And I navigate to "Allocate markers" in current page administration
    And I set the following fields to these values:
      | assessor_2_samplingstrategy | Automatic |
      | assessor_2_samplerules_0    | 1         |
      | assessor_2_sampletype_0     | grade     |
      | assessor_2_samplefrom_0     | 50        |
      | assessor_2_sampleto_0       | 100       |
    And I press "save_sampling"

    Then "student student1" row "Marker 2" column of "mod_coursework_allocatemarkers" table should contain "Automatically included in sample"
    And "student student2" row "Marker 2" column of "mod_coursework_allocatemarkers" table should not contain "Automatically included in sample"
    And "student student2" row "Marker 2" column of "mod_coursework_allocatemarkers" table should contain "Included in sample"

    When I navigate to "Allocate markers" in current page administration
    And I click on "Add rule" "link"
    And I set the following fields to these values:
      | assessor_2_samplingstrategy | Automatic |
      | assessor_2_samplerules_1    | 1         |
      | assessor_2_sampletype_1     | grade     |
      | assessor_2_samplefrom_1     | 40        |
      | assessor_2_sampleto_1       | 50        |
    And I press "save_sampling"

    Then "student student1" row "Marker 2" column of "mod_coursework_allocatemarkers" table should contain "Automatically included in sample"
    And "student student2" row "Marker 2" column of "mod_coursework_allocatemarkers" table should contain "Automatically included in sample"

    Given the following "mod_coursework > feedbacks" exist:
      | allocatable | coursework | assessor | stageidentifier | grade | feedbackcomment  |
      | student1    | Coursework | teacher1 | assessor_2      | 60    | New comment here |
      | student2    | Coursework | teacher1 | assessor_2      | 40    | New comment here |

    When I navigate to "Allocate markers" in current page administration
    And I click on "Add rule" "link"
    And I set the following fields to these values:
      | assessor_3_samplingstrategy | Automatic  |
      | assessor_3_samplerules_0    | 1          |
      | assessor_3_sampletype_0     | percentage |
      | assessor_3_samplefrom_0     | 60         |
      | assessor_3_sampleto_0       | 70         |
    And I press "save_sampling"

    Then "student student1" row "Marker 3" column of "mod_coursework_allocatemarkers" table should contain "Automatically included in sample"
    And "student student2" row "Marker 3" column of "mod_coursework_allocatemarkers" table should not contain "Automatically included in sample"
    And "student student2" row "Marker 3" column of "mod_coursework_allocatemarkers" table should contain "Included in sample"
