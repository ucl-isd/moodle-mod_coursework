@mod @mod_coursework @moderation-marking
Feature: Moderated marking - blind
  In order to ensure double marking works correctly
  As an admin
  I want to perform the full coursework workflow with moderated blind marking.

  Background:
    And the following "roles" exist:
      | shortname             | name                  | archetype |
      | courseworkexamoffice  | courseworkexamoffice  |           |
      | courseworkdbm         | courseworkdbm         |           |
      | courseworkmoderator   | courseworkmoderator   |           |
      | norole                | norole                |           |

    And the following "role capability" exists:
      | role                                          | courseworkexamoffice  |
      | mod/coursework:addinstance                    | allow                 |
      | moodle/role:assign                            | allow                 |
      | mod/coursework:addagreedgrade                 | allow                 |
      | mod/coursework:addallocatedagreedgrade        | allow                 |
      | mod/coursework:addgeneralfeedback             | allow                 |
      | mod/coursework:addinitialgrade                | allow                 |
      | mod/coursework:addplagiarismflag              | allow                 |
      | mod/coursework:administergrades               | allow                 |
      | mod/coursework:allocate                       | allow                 |
      | mod/coursework:canexportfinalgrades           | allow                 |
      | mod/coursework:editagreedgrade                | allow                 |
      | mod/coursework:editallocatedagreedgrade       | allow                 |
      | mod/coursework:editinitialgrade               | allow                 |
      | mod/coursework:editpersonaldeadline           | allow                 |
      | mod/coursework:grade                          | allow                 |
      | mod/coursework:grantextensions                | allow                 |
      | mod/coursework:moderate                       | allow                 |
      | mod/coursework:publish                        | allow                 |
      | mod/coursework:receivesubmissionnotifications | allow                 |
      | mod/coursework:revertfinalised                | allow                 |
      | mod/coursework:submitonbehalfof               | allow                 |
      | mod/coursework:updateplagiarismflag           | allow                 |
      | mod/coursework:view                           | allow                 |
      | mod/coursework:viewallgradesatalltimes        | allow                 |
      | mod/coursework:viewallstudents                | allow                 |
      | mod/coursework:viewanonymous                  | allow                 |
      | mod/coursework:viewextensions                 | allow                 |
      | moodle/course:manageactivities                | allow                 |
      | moodle/calendar:manageentries                 | allow                 |

    And the following "role capability" exists:
      | role                                          | courseworkdbm |
      | mod/coursework:addallocatedagreedgrade        | allow         |
      | mod/coursework:addgeneralfeedback             | allow         |
      | mod/coursework:addinitialgrade                | allow         |
      | mod/coursework:addplagiarismflag              | allow         |
      | mod/coursework:editagreedgrade                | allow         |
      | mod/coursework:editallocatedagreedgrade       | allow         |
      | mod/coursework:editinitialgrade               | allow         |
      | mod/coursework:grade                          | allow         |
      | mod/coursework:receivesubmissionnotifications | allow         |
      | mod/coursework:updateplagiarismflag           | allow         |
      | mod/coursework:view                           | allow         |
      | mod/coursework:viewextensions                 | allow         |

    And the following "role capability" exists:
      | role                                          | courseworkmoderator |
      | mod/coursework:addplagiarismflag              | allow               |
      | mod/coursework:grade                          | allow               |
      | mod/coursework:moderate                       | allow               |
      | mod/coursework:updateplagiarismflag           | allow               |
      | mod/coursework:view                           | allow               |
      | mod/coursework:viewallgradesatalltimes        | allow               |
      | mod/coursework:viewextensions                 | allow               |

    And the following "users" exist:
      | username    | firstname   | lastname | email                  |
      | manager     | Assessment  | Manager  | manager@example.com    |
      | marker1     | Marker      | 1        | marker1@example.com    |
      | marker2     | Marker      | 2        | marker2@example.com    |
      | marker3     | Marker      | 3        | marker3@example.com    |
      | moderator1  | Moderator   | 1        | moderator1@example.com |
      | student1    | Student     | 1        | student1@example.com   |
      | student2    | Student     | 2        | student2@example.com   |
      | student3    | Student     | 3        | student3@example.com   |

    Given there is a course
    And the following "course enrolments" exist:
      | user        | course | role                 |
      | manager     | C1     | courseworkexamoffice |
      | moderator1  | C1     | courseworkmoderator  |
      | moderator1  | C1     | teacher              |
      | marker1     | C1     | courseworkdbm        |
      | marker2     | C1     | courseworkdbm        |
      | marker3     | C1     | courseworkdbm        |
      | student1    | C1     | student              |
      | student2    | C1     | student              |
      | student3    | C1     | student              |

    Given there is a coursework
    And the coursework start date is now
    And the coursework "numberofmarkers" setting is "1" in the database
    And the coursework "blindmarking" setting is "1" in the database
    And the coursework "allocationenabled" setting is "1" in the database
    And the coursework "extensionsenabled" setting is "1" in the database
    And the coursework "allowlatesubmissions" setting is "1" in the database
    And the coursework "moderationagreementenabled" setting is "1" in the database
    And the coursework "filetypes" setting is "pdf" in the database
    And the coursework "assessorallocationstrategy" setting is "none" in the database

  Scenario: Moderate the assessment
    Given the following markers are allocated:
      | student   | assessor_1 | moderator   |
      | Student 1 | Marker 1   | Moderator 1 |
      | Student 2 | Marker 1   | Moderator 1 |
      | Student 3 | Marker 2   | Moderator 1 |

    And the student called "Student 1" has a finalised submission
    And the student called "Student 2" has a finalised submission
    And the student called "Student 3" has a finalised submission

    And the submission from "Student 1" is marked by "Marker 1" with:
      | Mark      | 70              |
      | Comment   | Excellent work! |
      | Finalised | 1               |

    And the submission from "Student 2" is marked by "Marker 1" with:
      | Mark      | 75              |
      | Comment   | Superb work!    |
      | Finalised | 1               |

    And the submission from "Student 3" is marked by "Marker 2" with:
      | Mark      | 50                  |
      | Comment   | I've seen worse...  |
      | Finalised | 1                   |

    And I am on the "Course 1" "course" page logged in as "moderator1"
    And I follow "Coursework 1"
    And I click on "Agree marking" "link" in the ".mod-coursework-submissions-row:nth-child(1)" "css_element"
    Then I should see "Moderation for"
    And I set the field "Moderation agreement" to "Agreed"
    And I press "Save changes"
    And I click on "Agree marking" "link" in the ".mod-coursework-submissions-row:nth-child(2)" "css_element"
    Then I should see "Moderation for"
    And I set the field "Moderation agreement" to "Disagreed"
    And I set the field "Comment" to "I don't like it!"
    And I press "Save changes"
    Then I should see "Agreed" in the "70" "table_row"
    Then I should see "Disagreed" in the "75" "table_row"
    When I follow "Disagreed"
    And I wait until the page is ready
    Then I should see "I don't like it!"

  Scenario: Check moderation
    Given the following markers are allocated:
      | student   | assessor_1 | moderator   |
      | Student 1 | Marker 1   | Moderator 1 |
      | Student 2 | Marker 1   | Moderator 1 |
      | Student 3 | Marker 2   | Moderator 1 |

    And the student called "Student 1" has a finalised submission
    And the student called "Student 2" has a finalised submission
    And the student called "Student 3" has a finalised submission

    And the submission from "Student 1" is marked by "Marker 1" with:
      | Mark      | 70              |
      | Comment   | Excellent work! |
      | Finalised | 1               |

    And the submission from "Student 2" is marked by "Marker 1" with:
      | Mark      | 75              |
      | Comment   | Superb work!    |
      | Finalised | 1               |

    And the submission from "Student 3" is marked by "Marker 2" with:
      | Mark      | 50                  |
      | Comment   | I've seen worse...  |
      | Finalised | 1                   |

    And the submission from "Student 1" is moderated by "Moderator 1" with:
      | Agreement | agreed               |

    And the submission from "Student 2" is moderated by "Moderator 1" with:
      | Agreement | disagreed               |
      | Comment   | I don't like it at all! |

    And I am on the "Course 1" "course" page logged in as "marker1"
    And I follow "Coursework 1"
    # See the moderation
    Then I should see "Moderation" in the "70" "table_row"
    Then I should see "Moderator 1" in the "70" "table_row"
    Then I should see "Agreed" in the "70" "table_row"
    Then I should see "Moderation" in the "75" "table_row"
    Then I should see "Moderator 1" in the "75" "table_row"
    Then I should see "Disagreed" in the "75" "table_row"

    # Update marking
    And I click on "75" "link" in the ".mod-coursework-submissions-row:nth-child(2)" "css_element"
    When I set the following fields to these values:
      | Mark    | 65              |
      | Comment | Updated mark    |
    And I press "Save as draft"
    Then I should see "Disagreed" in the "65" "table_row"

  @javascript
  Scenario: Release the grades
    Given the following markers are allocated:
      | student   | assessor_1 | moderator   |
      | Student 1 | Marker 1   | Moderator 1 |
      | Student 2 | Marker 1   | Moderator 1 |
      | Student 3 | Marker 2   | Moderator 1 |

    And the student called "Student 1" has a finalised submission
    And the student called "Student 2" has a finalised submission
    And the student called "Student 3" has a finalised submission

    And the submission from "Student 1" is marked by "Marker 1" with:
      | Mark      | 70              |
      | Comment   | Excellent work! |
      | Finalised | 1               |

    And the submission from "Student 2" is marked by "Marker 1" with:
      | Mark      | 75              |
      | Comment   | Superb work!    |
      | Finalised | 1               |

    And the submission from "Student 3" is marked by "Marker 2" with:
      | Mark      | 50                  |
      | Comment   | I've seen worse...  |
      | Finalised | 1                   |

    And the submission from "Student 1" is moderated by "Moderator 1" with:
      | Agreement | agreed               |

    And the submission from "Student 2" is moderated by "Moderator 1" with:
      | Agreement | disagreed               |
      | Comment   | I don't like it at all! |

    And I am on the "Course 1" "course" page logged in as "manager"
    And I follow "Coursework 1"
    Then I should see "Agreed" in the "70" "table_row"
    Then I should see "Disagreed" in the "75" "table_row"

    When I follow "Release the marks"
    Then I should see "Are you sure you want to release all marks?"
    And I press "Confirm"
    Then I should see "Marks released"
    Then I should see "Released" in the "70" "table_row"
    Then I should see "Released" in the "75" "table_row"
    Then I should see "Released" in the "50" "table_row"

  @javascript
  Scenario: Student 1 sees the released grades
    Given the following markers are allocated:
      | student   | assessor_1 | moderator   |
      | Student 1 | Marker 1   | Moderator 1 |
      | Student 2 | Marker 1   | Moderator 1 |
      | Student 3 | Marker 2   | Moderator 1 |

    And the student called "Student 1" has a finalised submission
    And the student called "Student 2" has a finalised submission
    And the student called "Student 3" has a finalised submission

    And the submission from "Student 1" is marked by "Marker 1" with:
      | Mark      | 70              |
      | Comment   | Excellent work! |
      | Finalised | 1               |

    And the submission from "Student 2" is marked by "Marker 1" with:
      | Mark      | 75              |
      | Comment   | Superb work!    |
      | Finalised | 1               |

    And the submission from "Student 3" is marked by "Marker 2" with:
      | Mark      | 50                  |
      | Comment   | I've seen worse...  |
      | Finalised | 1                   |

    And the submission from "Student 1" is moderated by "Moderator 1" with:
      | Agreement | agreed               |

    And the submission from "Student 2" is moderated by "Moderator 1" with:
      | Agreement | disagreed               |
      | Comment   | I don't like it at all! |

    And I am on the "Course 1" "course" page logged in as "manager"
    And I follow "Coursework 1"
    And I press the release marks button

    And I log out

    And I am on the "Course 1" "course" page logged in as "student1"
    And I follow "Coursework 1"
    Then I should see "Agreed feedback for Student 1"
    And I should see "Marker 1"
    And I should see "Excellent work!"
    And I should see "70"
    And I should see "Released"

  @javascript
  Scenario: Student 2 sees disagreed released grades
    Given the following markers are allocated:
      | student   | assessor_1 | moderator   |
      | Student 1 | Marker 1   | Moderator 1 |
      | Student 2 | Marker 1   | Moderator 1 |
      | Student 3 | Marker 2   | Moderator 1 |

    And the student called "Student 1" has a finalised submission
    And the student called "Student 2" has a finalised submission
    And the student called "Student 3" has a finalised submission

    And the submission from "Student 1" is marked by "Marker 1" with:
      | Mark      | 70              |
      | Comment   | Excellent work! |
      | Finalised | 1               |

    And the submission from "Student 2" is marked by "Marker 1" with:
      | Mark      | 75              |
      | Comment   | Superb work!    |
      | Finalised | 1               |

    And the submission from "Student 3" is marked by "Marker 2" with:
      | Mark      | 50                  |
      | Comment   | I've seen worse...  |
      | Finalised | 1                   |

    And the submission from "Student 1" is moderated by "Moderator 1" with:
      | Agreement | agreed               |

    And the submission from "Student 2" is moderated by "Moderator 1" with:
      | Agreement | disagreed               |
      | Comment   | I don't like it at all! |

    And I am on the "Course 1" "course" page logged in as "manager"
    And I follow "Coursework 1"
    And I press the release marks button

    Then I should see "Disagreed" in the "75" "table_row"
    Then I should see "Released" in the "75" "table_row"

    And I log out

    # Student can see released feedback even its was not agreed on.
    And I am on the "Course 1" "course" page logged in as "student2"
    And I follow "Coursework 1"
    Then I should see "Agreed feedback for Student 2"
    And I should see "Marker 1"
    And I should see "Superb work!"
    And I should see "75"
    And I should see "Released"

  @javascript
  Scenario: Check moderation form
    Given the following markers are allocated:
      | student   | assessor_1 | moderator   |
      | Student 1 | Marker 1   | Moderator 1 |
      | Student 2 | Marker 1   | Moderator 1 |
      | Student 3 | Marker 2   | Moderator 1 |

    And the student called "Student 1" has a finalised submission
    And the student called "Student 2" has a finalised submission
    And the student called "Student 3" has a finalised submission

    And the submission from "Student 1" is marked by "Marker 1" with:
      | Mark      | 70              |
      | Comment   | Excellent work! |
      | Finalised | 1               |

    And the submission from "Student 2" is marked by "Marker 1" with:
      | Mark      | 75              |
      | Comment   | Superb work!    |
      | Finalised | 1               |

    And the submission from "Student 3" is marked by "Marker 2" with:
      | Mark      | 50                  |
      | Comment   | I've seen worse...  |
      | Finalised | 1                   |

    And the submission from "Student 1" is moderated by "Moderator 1" with:
      | Agreement | agreed               |

    And the submission from "Student 2" is moderated by "Moderator 1" with:
      | Agreement | disagreed               |
      | Comment   | I don't like it at all! |

    # Before marks have been released moderator can view and edit moderation.
    And I am on the "Course 1" "course" page logged in as "moderator1"
    And I follow "Coursework 1"
    And I click on "Agreed" "link" in the ".mod-coursework-submissions-row:nth-child(1)" "css_element"
    Then I should see "Moderation for "
    And I should see "Moderator 1"
    And "Save changes" "button" should exist

    And I am on the "Course 1" "course" page logged in as "manager"
    And I follow "Coursework 1"
    And I press the release marks button
    And I log out

    # After marks have been released moderator can view but not edit moderation.
    And I am on the "Course 1" "course" page logged in as "moderator1"
    And I follow "Coursework 1"
    And I click on "Agreed" "link" in the ".mod-coursework-submissions-row:nth-child(1)" "css_element"
    Then I should see "Moderation for "
    And I should see "Moderator 1"
    And "Save changes" "button" should not exist
