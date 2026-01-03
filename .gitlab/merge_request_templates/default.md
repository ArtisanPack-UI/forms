<!--- Provide a clear, descriptive title for this merge request -->

/label ~"Status::In Review"

## Description

<!--- Describe your changes in detail -->

**Closes:** #<!-- issue number -->

## Type of Change

<!--- Select the type that applies -->

- [ ] Bug fix (fixes an issue)
- [ ] New feature (adds new functionality)
- [ ] Enhancement (improves existing functionality)
- [ ] Refactoring (code improvement, no behavior change)
- [ ] Documentation update
- [ ] Performance improvement
- [ ] Security fix
- [ ] Breaking change (breaks backward compatibility)

## Related Issue

<!--- This project only accepts merge requests related to open issues -->
<!--- Link to the issue this MR addresses -->

**Issue:** #

## Motivation and Context

<!--- Why is this change required? What problem does it solve? -->

## Changes Made

<!--- Bullet point list of key changes -->

- 
- 
- 

## How Has This Been Tested?

<!--- Describe in detail how you tested your changes -->
<!--- Include your testing environment and the tests you ran -->

**Testing Environment:**
- Operating System:
- Browser (if applicable):
- PHP Version:
- Project Version:

**Tests Performed:**
1. 
2. 
3. 

## Accessibility Tests Run

<!--- REQUIRED: Describe accessibility testing performed -->

- [ ] Keyboard navigation tested
- [ ] Screen reader tested
- [ ] Color contrast verified
- [ ] ARIA labels checked

**Details:**

## Tests Added

<!--- If applicable, describe tests added -->
<!--- If no tests needed, explain why -->

- [ ] Unit tests added/updated
- [ ] Integration tests added/updated
- [ ] All tests passing

**Test details:**

## Documentation

<!--- If applicable, describe documentation changes -->
<!--- If no documentation needed, explain why -->

- [ ] Inline code documentation added
- [ ] README updated
- [ ] Wiki updated
- [ ] API documentation updated

**Documentation details:**

## Screenshots

<!--- If appropriate, add screenshots showing the changes -->

## Pre-Submission Checklist

Before requesting review, confirm:

- [ ] Followed contributing guidelines
- [ ] Checked for other open MRs for same update
- [ ] Code passes all tests
- [ ] Code has been linted
- [ ] Accessibility tests completed
- [ ] Code follows project style guide
- [ ] Self-review completed
- [ ] Comments added for complex code
- [ ] No new warnings generated

## Labels

Ensure these labels are applied:

- [ ] `Status::In Review` (automatically applied)
- [ ] `Type::*` (Bug, Feature, Enhancement, etc.)
- [ ] `Area::*` (Frontend, Backend, etc.)
- [ ] `Priority::*` (if urgent)

**Quick Actions Available:**
```
/assign @reviewer              # Assign reviewer
/label ~"Type::Bug"           # If bug fix
/label ~"Type::Feature"       # If new feature
/label ~"Area::Frontend"      # If UI changes
/label ~"Area::Backend"       # If API changes
/label ~"breaking change"     # If breaks compatibility
/milestone %v1.0              # Assign to milestone
```

## Reviewer Notes

<!--- Anything specific reviewers should focus on? -->

## Deployment Notes

<!--- Any special deployment considerations? -->
<!--- Database migrations? Configuration changes? Environment variables? -->

---

**For Reviewers:**  
After approval, change label to `Status::Approved` before merging.