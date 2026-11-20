@mod @mod_coursework @mod_coursework_markingallocation
Feature: Give the marker role to teaching staff when uploading allocations

  In order to allocate markers in bulk without assigning roles one by one
  As a teacher uploading marker allocations
  I want to be told which teaching staff in the file do not have the marker role, and to give it to them

  Background:
    Given the following "roles" exist:
      | shortname | name              |
      | cwmarker  | Coursework marker |
    And the following "role capabilities" exist:
      | role     | mod/coursework:addinitialgrade | mod/coursework:view |
      | cwmarker | allow                          | allow               |
    And the following config values are set as admin:
      | allocation_identifier | username | mod_coursework |
    And the following "course" exists:
      | fullname  | Course 1 |
      | shortname | C1       |
    And the following "activity" exists:
      | activity                   | coursework |
      | course                     | C1         |
      | name                       | Coursework |
      | allocationenabled          | 1          |
      | assessorallocationstrategy | none       |
      | numberofmarkers            | 2          |
    And the following "users" exist:
      | username  | firstname | lastname |
      | teacher_a | Teacher   | A        |
      | teacher_b | Teacher   | B        |
      | tutor_x   | Tara      | Tutor    |
      | tutor_y   | Yusuf     | Tutor    |
      | student_a | Student   | A        |
      | student_b | Student   | B        |
      | student_c | Student   | C        |
    And the following "course enrolments" exist:
      | user      | course | role           |
      | teacher_a | C1     | editingteacher |
      | teacher_b | C1     | editingteacher |
      | tutor_x   | C1     | teacher        |
      | tutor_y   | C1     | teacher        |
      | student_a | C1     | student        |
      | student_b | C1     | student        |
      | student_c | C1     | student        |
    # Non-editing teachers cannot mark in this course until they are given the marker role.
    And the following "permission overrides" exist:
      | capability                     | permission | role           | contextlevel | reference |
      | mod/coursework:addinitialgrade | Prevent    | teacher        | Course       | C1        |
      | mod/coursework:allocate        | Allow      | editingteacher | Course       | C1        |
    And I log in as "admin"
    And I navigate to "Plugins > Activity modules > Coursework" in site administration
    And I set the field "Marker role" to "Coursework marker"
    And I press "Save changes"

  @javascript @_file_upload
  Scenario: Teaching staff without the marker role are listed, and confirming gives them the role and allocates them
    Given I define the allowed role assignments for the "Teacher" role as:
      | Coursework marker | Assignable |
    And I log out
    And I am on the "Coursework" "coursework activity" page logged in as "teacher_a"
    And I navigate to "Allocate markers" in current page administration
    And I follow "Upload allocations"
    And I upload "mod/coursework/tests/files_for_uploading/allocations_marker_role.csv" file to "Allocations file" filemanager
    When I press "Upload allocations"
    Then I should see "Tara Tutor (tutor_x)" in the "region-main" "region"
    And I should see "Yusuf Tutor (tutor_y)" in the "region-main" "region"
    # teacher_b can already mark and student_a has no teaching role, so neither is offered the role.
    And I should not see "teacher_b" in the "region-main" "region"
    And I should not see "student_a" in the "region-main" "region"
    When I press "Assign role and allocate"
    Then I should see "Marker 'student_a' not in the coursework"
    And I should not see "Marker 'tutor_x' not in the coursework"
    And I should not see "Marker 'tutor_y' not in the coursework"
    And I am on the "Coursework" "coursework activity" page logged in as "admin"
    And I should see "Teacher B" in the "Student A" "table_row"
    And I should see "Tara Tutor" in the "Student A" "table_row"
    And I should see "Yusuf Tutor" in the "Student B" "table_row"
    And I should see "Teacher B" in the "Student B" "table_row"

  @javascript @_file_upload
  Scenario: Cancelling the confirmation changes nothing
    Given I define the allowed role assignments for the "Teacher" role as:
      | Coursework marker | Assignable |
    And I log out
    And I am on the "Coursework" "coursework activity" page logged in as "teacher_a"
    And I navigate to "Allocate markers" in current page administration
    And I follow "Upload allocations"
    And I upload "mod/coursework/tests/files_for_uploading/allocations_marker_role.csv" file to "Allocations file" filemanager
    And I press "Upload allocations"
    When I press "Cancel"
    Then I should see "Allocations file"
    And I am on the "Coursework" "coursework activity" page logged in as "admin"
    And I should not see "Tara Tutor" in the "Student A" "table_row"
    And I should not see "Teacher B" in the "Student A" "table_row"
    And I should not see "Yusuf Tutor" in the "Student B" "table_row"

  @javascript @_file_upload
  Scenario: Uploaders who cannot assign the marker role get the previous behaviour
    Given I log out
    And I am on the "Coursework" "coursework activity" page logged in as "teacher_a"
    And I navigate to "Allocate markers" in current page administration
    And I follow "Upload allocations"
    And I upload "mod/coursework/tests/files_for_uploading/allocations_marker_role.csv" file to "Allocations file" filemanager
    When I press "Upload allocations"
    Then I should not see "Assign role and allocate"
    And I should see "Marker 'tutor_x' not in the coursework"
    And I should see "Marker 'tutor_y' not in the coursework"
    And I should see "Marker 'student_a' not in the coursework"
    And I am on the "Coursework" "coursework activity" page logged in as "admin"
    And I should not see "Tara Tutor" in the "Student A" "table_row"
    And I should not see "Yusuf Tutor" in the "Student B" "table_row"

  @javascript @_file_upload
  Scenario: Nobody is offered the role when no marker role is configured
    # The background leaves the admin on the Coursework settings page.
    Given I set the field "Marker role" to "None"
    And I press "Save changes"
    And I define the allowed role assignments for the "Teacher" role as:
      | Coursework marker | Assignable |
    And I log out
    And I am on the "Coursework" "coursework activity" page logged in as "teacher_a"
    And I navigate to "Allocate markers" in current page administration
    And I follow "Upload allocations"
    And I upload "mod/coursework/tests/files_for_uploading/allocations_marker_role.csv" file to "Allocations file" filemanager
    When I press "Upload allocations"
    Then I should not see "Assign role and allocate"
    And I should see "Marker 'tutor_x' not in the coursework"
