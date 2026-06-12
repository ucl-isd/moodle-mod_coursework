@mod @mod_coursework @mod_coursework_sampling
Feature: Agree marking for submissions not in sample

  CTP-6337: In a coursework with three markers and sampling enabled, a submission that has received two finalised marks but was
  never added to the sample must show the "Agree marking" button without requiring a third mark.
  Make sure it does not show Marker 3.

  Background:
    Given the following "course" exists:
      | fullname  | Course 1 |
      | shortname | C1       |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | manager   | manager1 | manager1@example.com |
      | teacher1 | teacher   | teacher1 | teacher1@example.com |
      | teacher2 | teacher   | teacher2 | teacher2@example.com |
      | student1 | student   | student1 | student1@example.com |
      | student2 | student   | student2 | student2@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | manager1 | C1     | manager |
      | teacher1 | C1     | teacher |
      | teacher2 | C1     | teacher |
      | student1 | C1     | student |
      | student2 | C1     | student |
    And the following "activity" exists:
      | activity          | coursework   |
      | course            | C1           |
      | name              | Coursework   |
      | numberofmarkers   | 3            |
      | samplingenabled   | 1            |
      | allocationenabled | 0            |
      | deadline          | ##-1 week##  |
    And the following "mod_coursework > submissions" exist:
      | allocatable | coursework | finalisedstatus |
      | student1    | Coursework | 1               |
      | student2    | Coursework | 1               |
    And the following "mod_coursework > feedbacks" exist:
      | allocatable | coursework | assessor | stageidentifier | grade | feedbackcomment | finalised |
      | student1    | Coursework | teacher1 | assessor_1      | 50    | First mark      | 1         |
      | student1    | Coursework | teacher2 | assessor_2      | 50    | Second mark     | 1         |
      | student2    | Coursework | teacher1 | assessor_1      | 95    | First mark      | 1         |
      | student2    | Coursework | teacher2 | assessor_2      | 99    | Second mark     | 1         |

  Scenario: Submission not in a sample shows agree button and no third marker
    Given I am on the "Coursework" "coursework activity" page logged in as "manager1"
    Then I should see "Agree marking" in the "student student1" "table_row"
    And I should not see "Marker 3" in the "student student1" "table_row"

  @javascript
  Scenario: Submissions with 2 marker feedbacks and sampling allocations created
    Given I am on the "Coursework" "coursework activity" page logged in as "manager1"
    And I navigate to "Allocate markers" in current page administration
    And I set the following fields to these values:
      | assessor_2_samplingstrategy | Automatic |
      | assessor_2_samplerules_0    | 1         |
      | assessor_2_sampletype_0     | grade     |
      | assessor_2_samplefrom_0     | 90        |
      | assessor_2_sampleto_0       | 100       |
      | assessor_3_samplingstrategy | Automatic |
      | assessor_3_samplerules_0    | 1         |
      | assessor_3_sampletype_0     | grade     |
      | assessor_3_samplefrom_0     | 90        |
      | assessor_3_sampleto_0       | 100       |
    When I press "save_sampling"
    Then "student student2" row "Marker 3" column of "mod_coursework_allocatemarkers" table should contain "Automatically included in sample"
    And "student student1" row "Marker 3" column of "mod_coursework_allocatemarkers" table should not contain "Automatically included in sample"
    When I am on the "Coursework" "coursework activity" page
    Then I should see "Agree marking" in the "student student1" "table_row"
    And I should not see "Marker 3" in the "student student1" "table_row"
    And I should not see "Agree marking" in the "student student2" "table_row"
    And I should see "Marker 3" in the "student student2" "table_row"
