@mod @mod_coursework @workbench
Feature: Double marking - blind
  In order to ensure double marking works correctly
  As an admin
  I want to perform the full coursework workflow with blind marking.

  Background:
    Given the following "custom field categories" exist:
      | name | component   | area   | itemid |
      | CLC  | core_course | course | 0      |
    And the following "custom fields" exist:
      | name        | shortname   | category | type |
      | Course Year | course_year | CLC      | text |
    And the following "courses" exist:
      | fullname | shortname | format | customfield_course_year |
      | Course 1 | C1        | topics | ##now##%Y##             |
    And the following "roles" exist:
      | shortname           | name                | archetype |
      | courseworkmarker    | courseworkmarker    | teacher   |
    And the following "users" exist:
      | username  | firstname | lastname | email                |
      | teacher1  | teacher   | 1        | teacher1@example.com |
      | marker1   | marker    | 1        | marker1@example.com  |
      | marker2   | marker    | 2        | marker2@example.com  |
      | marker3   | marker    | 3        | marker3@example.com  |
      | student1  | Student   | 1        | student1@example.com |
    And the following "course enrolments" exist:
      | user      | course | role             |
      | teacher1  | C1     | teacher          |
      | marker1   | C1     | teacher          |
      | marker2   | C1     | teacher          |
      | marker3   | C1     | teacher          |
      | student1  | C1     | student          |

  Scenario: Create coursework assignment with double marking
    Given I am on the "Course 1" "course" page logged in as "admin"
    When I add a coursework activity to course "Course 1" section "2" and I fill the form with:
      | Coursework title                                            | Coursework – Double marking blind                 |
      | Formative or summative?                                     | Summative - counts towards the final module mark  |
      | Description                                                 | Test coursework description                       |
      | Display description on course page                          | Yes                                               |
      | Start date                                                  | ##now##                                           |
      | Deadline for submissions:                                   | ##now + 15 minutes##                              |
      | Use marking deadline                                        | Yes                                               |
      | Types of file that students are allowed to submit           | pdf                                               |
      | Enable plagiarism flagging                                  | Yes                                               |
      | Number of times each submission should initially be marked. | 2 |
      | Marker allocation enabled                                   | Yes |
      | Marker allocation strategy                                  | Manual |
      | Automatic agreement of marks                                  | percentage distance |
      | Automatic agreement range                                   | 10 |
      | View initial markers' grades                                | No |
      | Auto-populate agreed feedback comment                       | Yes |
      | Blind marking                                               | Yes |
      | Marker anonymity                                            | Yes |
      | Blind marking                                               | Yes |

    Then I should see "Coursework – Double marking blind"

  Scenario: Add markers
    Given I am on the "Course 1" "course" page logged in as "admin"
    When I add a coursework activity to course "Course 1" section "2" and I fill the form with:
      | Coursework title                                            | Coursework – Double marking blind                 |
      | Formative or summative?                                     | Summative - counts towards the final module mark  |
      | Description                                                 | Test coursework description                       |
      | Display description on course page                          | Yes                                               |
      | Start date                                                  | ##now##                                           |
      | Deadline for submissions:                                   | ##now + 15 minutes##                              |
      | Use marking deadline                                        | Yes                                               |
      | Types of file that students are allowed to submit           | pdf                                               |
      | Enable plagiarism flagging                                  | Yes                                               |
      | Number of times each submission should initially be marked. | 2 |
      | Marker allocation enabled                                   | Yes |
      | Marker allocation strategy                                  | Manual |
      | Automatic agreement of marks                                  | percentage distance |
      | Automatic agreement range                                   | 10 |
      | View initial markers' grades                                | No |
      | Auto-populate agreed feedback comment                       | Yes |
      | Blind marking                                               | Yes |
      | Marker anonymity                                            | Yes |
      | Blind marking                                               | Yes |

    And I follow "Coursework – Double marking blind"
    And I follow "Add markers"
    And I follow "courseworkmarker"

    Then I should see "marker 1" in the "Potential users" "field"
    And I should see "marker 2" in the "Potential users" "field"
    And I should see "marker 3" in the "Potential users" "field"

    When I set the field "Potential users" to "marker 1 (marker1@example.com)"
    And I press "Add"
    And I set the field "Potential users" to "marker 2 (marker2@example.com)"
    And I press "Add"
    And I set the field "Potential users" to "marker 3 (marker3@example.com)"
    And I press "Add"

    Then I should see "marker 1" in the "Existing users" "field"
    And I should see "marker 2" in the "Existing users" "field"
    And I should see "marker 3" in the "Existing users" "field"
    And I should not see "marker 1" in the "Potential users" "field"
    And I should not see "marker 2" in the "Potential users" "field"
    And I should not see "marker 3" in the "Potential users" "field"
