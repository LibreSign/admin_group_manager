# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and follows the requirements of the [Nextcloud Appstore Metadata specification](https://nextcloudappstore.readthedocs.io/en/latest/developer.html#changelog).

Types of changes:
- *Added* for new features.
- *Changed* for changes in existing functionality.
- *Deprecated* for soon-to-be removed features.
- *Removed* for now removed features.
- *Fixed* for any bug fixes.
- *Security* in case of vulnerabilities. 

<!-- changelog-linker -->
## Unreleased
### Security
* Enabling the users of a group, on `users-of-group/set-enabled` or when the group is provisioned again, now only enables the users that `users-of-group/set-enabled` disabled. Users disabled by the group admin stay disabled. Users disabled by billing before this version are not enabled again automatically; the group admin enables them in the users settings.

## 0.1.1 - 2026-03-12
### Changed
* Declare compatibility with Nextcloud 33.

## 0.1.0 - 2024-10-04
### New feature
* Hi
