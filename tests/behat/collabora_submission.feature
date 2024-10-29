@mod @mod_assign @assignsubmission @assignsubmission_collabora

Feature: In an assignment, students can use collabora to make a submission
  In order to complete my assignments using collabora features
  As a student
  I want to make use of collabora features to make an assignment submission

  Background:
    Given the following config values are set as admin:
      | disabled | 0 | assignsubmission_collabora |
    # for javascript login to work
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category | groupmode | format | coursedisplay |
      | Course 1 | C1        | 0        | 1         | topics | 0             |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "groups" exist:
      | name   | course | idnumber |
      | Group1 | C1     | GC11     |
    And the following "groupings" exist:
      | name       | course | idnumber |
      | Grouping 1 | C1     | GG1      |
    And the following "grouping groups" exist:
      | grouping | group |
      | GG1      | GC11  |
    And the following "group members" exist:
      | user     | group |
      | student1 | GC11  |

  @javascript
  Scenario: Make a collabora submission for an assignment.
    # Create our collabora assignment
    When I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add a Assignment to course "Course 1" section "1" and I fill the form with:
      | Assignment name                     | Collabora Test Assignment                                 |
      | Description                         | "Lorem ipsum dolor sit amet, consectetur adipiscing elit" |
      | assignsubmission_onlinetext_enabled | 0                                                         |
      | assignsubmission_file_enabled       | 0                                                         |
      | assignsubmission_collabora_enabled  | 1                                                         |
      | assignsubmission_collabora_format   | spreadsheet                                               |
      | assignsubmission_collabora_filename | testcollaborafile                                         |
    And I log out
    # Make the collabora assignment submission
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I click on "Collabora Test Assignment" "link" in the "#section-1" "css_element"
    And I press "Add submission"
    And I edit my collabora assign submission document
    And I press "Save changes"
    And I should see "Submitted for grading"
    And I should see "Not graded"
    And I log out
    # View the grading status of the collabora assignment submission
    # So that it can be graded - Grading is tested by the assignment module.
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I click on "Collabora Test Assignment" "link" in the "#section-1" "css_element"
    And I click on "Grade" "link" in the "#region-main" "css_element"
    And I should see "Student 1"
    And I should see "Submitted for grading"
    And I should see "testcollaborafile.xlsx"
    And I log out

  @javascript
  Scenario: Make a collabora submission for an assignment as a member of a group.
    # Create our collabora assignment
    When I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add a Assignment to course "Course 1" section "1" and I fill the form with:
      | Assignment name                     | Collabora Test Assignment                                 |
      | Description                         | "Lorem ipsum dolor sit amet, consectetur adipiscing elit" |
      | assignsubmission_onlinetext_enabled | 0                                                         |
      | assignsubmission_file_enabled       | 0                                                         |
      | assignsubmission_collabora_enabled  | 1                                                         |
      | assignsubmission_collabora_format   | spreadsheet                                               |
      | assignsubmission_collabora_filename | testcollaborafile                                         |
      | teamsubmission                      | 1                                                         |
      | preventsubmissionnotingroup         | 1                                                         |
      | teamsubmissiongroupingid            | Grouping 1                                                |
    And I log out
    # Make the collabora assignment submission
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I click on "Collabora Test Assignment" "link" in the "#section-1" "css_element"
    And I press "Add submission"
    And I edit my collabora assign submission document
    And I press "Save changes"
    And I should see "Submitted for grading"
    And I should see "Not graded"
    And I log out
    # View the grading status of the collabora assignment submission
    # So that it can be graded - Grading is tested by the assignment module.
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I click on "Collabora Test Assignment" "link" in the "#section-1" "css_element"
    And I click on "Grade" "link" in the "#region-main" "css_element"
    And I should see "Student 1"
    And I should see "Submitted for grading"
    And I should see "testcollaborafile.xlsx"
    And I log out
