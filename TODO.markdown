* Write tests for:
  * `Resque_Failure`
  * `Resque_Failure_Redis`
* Plugin/hook type system similar to Ruby version (when done, implement the
setUp and tearDown methods as a plugin)
* Change to preforking worker model
* Clean up /bin and /demo
* Add a way to store arbitrary text in job statuses (for things like progress
indicators)
* Write plugin for Ruby resque that calls setUp and tearDown methods