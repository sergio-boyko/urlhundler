<?php
/*******************************************************************************
* Copyright (c) 2011-2014 by Matt Wright and contributors
* https://github.com/mattwright/URLResolver.php
*
* Licensed under The MIT License
* Redistributions of files must retain the below copyright notice.
*
* Permission is hereby granted, free of charge, to any person obtaining a copy
* of this software and associated documentation files (the "Software"), to deal
* in the Software without restriction, including without limitation the rights
* to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
* copies of the Software, and to permit persons to whom the Software is
* furnished to do so, subject to the following conditions:
*
* The above copyright notice and this permission notice shall be included in
* all copies or substantial portions of the Software.
*
* THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
* IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
* FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
* AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
* LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
* OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
* THE SOFTWARE.
*******************************************************************************/
class URLRedirect {
	private $curl;
	private $error;
	private $is_debug = false;
	private $user_agent;
	private $cookie_jar;
	private $request_timeout = 30;
	private $max_redirects = 10;
	private $max_response_data_size = 120000;
	private $is_multiple = false;

	# You must pass this function a filename to use as the cookie jar.
	# An exception will be thrown if the file exists or cannot be created.
	# This is because the library will also remove this file at the end
	# and we don't want to accidentally destroy any of your files.
	public function setUserAgent($user_agent_string) {
		if (is_string($user_agent_string) && strlen($user_agent_string)) {
			$this->user_agent = $user_agent_string;
		}
		else {
			throw new Exception('URLResolver->setUserAgent() must be called with a string');
		}

		$this->closeCurl(); # Reset curl with new settings...
	}
	public function setCookieJar($cookie_jar_filename, $overwrite = false) {
		if (!$overwrite && file_exists($cookie_jar_filename)) {
			throw new Exception("URLResolver->setCookieJar() founding existing file $cookie_jar_filename.\nPass true as second argument to overwrite and delete.");
		}

		if (file_put_contents($cookie_jar_filename, '') === false) {
			throw new Exception("URLResolver->setCookieJar() could not write to $cookie_jar_filename");
		}

		$this->cookie_jar = $cookie_jar_filename;

		$this->closeCurl(); # Reset curl with new settings...
	}
	public function setRequestTimeout($seconds) {
		if (is_numeric($seconds) && (int)$seconds == $seconds) {
			$this->request_timeout = (int)$seconds;
		}
		else {
			throw new Exception('URLResolver->setRequestTimeout() must be called with an integer');
		}

		$this->closeCurl(); # Reset curl with new settings...
	}
	public function setMaxRedirects($max_redirects) {
		if (is_numeric($max_redirects)) {
			$this->max_redirects = (int)$max_redirects;
		}
		else {
			throw new Exception('URLResolver->setMaxRedirects() must be called with an integer');
		}

		$this->closeCurl(); # Reset curl with new settings...
	}
	public function setMaxResponseDataSize($max_bytes) {
		if (is_numeric($max_bytes)) {
			$this->max_response_data_size = (int)$max_bytes;
		}
		else {
			throw new Exception('URLResolver->setMaxResponseDataSize() must be called with an integer');
		}

		$this->closeCurl(); # Reset curl with new settings...
	}
	public function isDebugMode($value) {
		if (isset($value)) { $this->is_debug = $value ? true : false; }
		return $this->is_debug;
	}
	public function isMultiple($value) {
		if (isset($value)) { 
			$this->is_multiple = $value ? true : false;
		}
	}
	public function resolveURL($url) {
		$starting_url     = $url;
		$url_results      = array();
		$status_codes_arr = array();
		$urls_arr         = array();

		for ($i = 0; $i < $this->max_redirects; $i++) {
			# During debug mode, print out each URL that we visit.
			if ($this->is_debug) {
				if ($i) { print ' |- '; }
				print $url;
			}
			# Fetch the redirect information...
			$url_result = $this->fetchURLResult($url);
			# Mark this as the starting URL if it is the first or equals that URL
			if ($i == 0 || $url == $starting_url) 
				$url_result->isStartingURL(true);
			# Also print a short status line regarding the URL once it is fetched
			if ($this->is_debug) 
				print ' ' . $url_result->debugStatus() . "\n";

			# If an error occurs during the processing of this url, return
			# the result when that error happens
			if ($url_result->didErrorOccur()) {
				if ($this->is_debug) {
					print ' |! ' . $url_result->getURL() . ' ' . $url_result->debugStatus() . "\n";
					if ($url_result->didErrorOccur()) { print ' \->  ' . $url_result->getErrorMessageString() . "\n"; }
					print "\n";
				}
				$this->closeCurl();
				return $url_result;
			}
			
			$next_url = $url_result->getRedirectTarget();
			$next_url_visited_count = 0;
			foreach ($url_results as $previous_result) {
				# If this result was for the same URL with the same status, then we have looped.
				# We need to check the status as well, because in some cases we may get
				# multiple redirected to establish cookies (New York Times) and so when we
				# return to the same page, we will have a different status (200 instead of 301)
				# and we will still want to check for the og:url in that case...
				if ($previous_result->getURL() == $url_result->getURL() &&
				    $previous_result->getHTTPStatusCode() == $url_result->getHTTPStatusCode()) {

					return $this->resolveURLResults($url_results);
				}
				# If the next URL to fetch has been previously fetched, decide whether to try again
				if (isset($next_url) && $next_url == $previous_result->getURL()) {
					$next_url_visited_count++;
					# We are done if we have already visited this URL twice. (looped)
					if ($next_url_visited_count > 1) 
						return $this->resolveURLResults($url_results);
					# We are also done if we have been to this URL and it wasn't a redirect
					# (it could have been an og:url or a rel=canonical) (looped)
					if ( ! $previous_result->hasRedirectHTTPStatus())
						return $this->resolveURLResults($url_results);
				}
			}//endforeach

			array_push($urls_arr, $url_result->getURL());
			array_push($url_results, $url_result);
			array_push($status_codes_arr, $url_result->getHTTPStatusCode());
			
			$url_result->addHTTPStatusCodes($status_codes_arr);
			$url_result->addURLS($urls_arr);

			if(in_array($next_url, $urls_arr)) 
				$url_result->hasLooped(true);
			# If there is no next URL set, we're done.
			if ( ! isset($next_url)) 			
				return $this->resolveURLResults($url_results);
			
			$url = $next_url;			
		}//endfor
		
		return $this->resolveURLResults($url_results);
	}
	private function resolveURLResults($url_results) {
		# If no URL results were found, return null as failure...
		if (!isset($url_results) || count($url_results) < 1) return null;

		$ok_url_result = $fail_url_result = $redirect_url_result = null;

		foreach (array_reverse($url_results) as $url_result) {
			if ($url_result->hasSuccessHTTPStatus()) {
				if ( ! $ok_url_result) 
					$ok_url_result = $url_result;				
			}
			# If the URL had a redirect status, then we set that result type
			else if ($url_result->hasRedirectHTTPStatus()) {
				if (!$redirect_url_result) 
					$redirect_url_result = $url_result;
			}
			# Only set a failure result if it didn't have success or redirect status code
			else if (!$fail_url_result)
				$fail_url_result = $url_result;
		}

		# Start with the result from our initial url
		$return_url = $url_results[0];

		# Following that, we will take any status=200 link
		if (isset($ok_url_result))
			$return_url = $ok_url_result;
		# Better to end in an HTTP failure than a redirect
		else if (isset($fail_url_result)) 
			$return_url = $fail_url_result;
		# We will still take the deepest redirect found if needed
		else if (isset($redirect_url_result))
			$return_url = $redirect_url_result;
		# Reset everything so that we start with a clean slate, cookie jar, etc. next time
		$this->closeCurl();

		if ($this->is_debug)
			print ' |> ' . $return_url->getURL() . ' ' . $return_url->debugStatus() . "\n\n";

		return $return_url;
	}
	private function fetchURL($url) {
		$curl = $this->initCurl();
		curl_setopt($curl, CURLOPT_URL, $url);

		# Limit the body to 1,000,000 bytes
		$headers = $body = '';
		$header_length = 0;
		$max_data_length = $this->max_response_data_size;
		curl_setopt($curl, CURLOPT_WRITEFUNCTION, function($handle, $data) use (&$headers, &$body, &$header_length, $max_data_length) {
			$body .= $data;

			if ($headers == '') {
				$headers_end = strpos($body, "\r\n\r\n");
				if ($headers_end !== false) {
					$header_length = $headers_end;
					$headers = substr($body, 0, $header_length);
					$body = substr($body, $header_length + 4);

					# Now that we have headers, if the content type is not HTML, we do
					# not need to download anything else. Prevents us from downloading
					# images, videos, PDFs, etc. that won't contain redirects

					# Until PHP 5.4, you can't import $this lexical variable into a closure,
					# so we will need to duplicate code from contentTypeFromHeader()
					# and hasHTMLContentType()
					if (preg_match('/^\s*Content-Type:\s*([^\s;\n]+)/im', $headers, $matches)) {
						if (stripos($matches[1], 'html') === false) { return 0; }
					}
				}
			}

			# If we have downloaded the maximum amount of content, we're done.
			if (($header_length + strlen($body)) > $max_data_length) { return 0; }

			return strlen($data);
		});


		curl_exec($curl);

		if ($headers === '') { return array(null, null); }

		return array($headers, $body);
	}
	private function fullyQualifyURI($uri, $url) {
		$uri = trim($uri);
		# Only use this if it looks like a URL/URI (starts with / or https?://)
		# Otherwise, we won't be able to understand it.
		//if ( ! preg_match('/^\s*(\/|https?:\/\/)/i', $uri)) return null;
		if ( ! preg_match('%^(?:(?:https?)://)(?:\S+(?::\S*)?@)?(?:(?!(?:10|127)(?:\.\d{1,3}){3})(?!(?:169\.254|192\.168)(?:\.\d{1,3}){2})(?!172\.(?:1[6-9]|2\d|3[0-1])(?:\.\d{1,3}){2})(?:[1-9]\d?|1\d\d|2[01]\d|22[0-3])(?:\.(?:1?\d{1,2}|2[0-4]\d|25[0-5])){2}(?:\.(?:[1-9]\d?|1\d\d|2[0-4]\d|25[0-4]))|(?:(?:[a-z\x{00a1}-\x{ffff}0-9]-*)*[a-z\x{00a1}-\x{ffff}0-9]+)(?:\.(?:[a-z\x{00a1}-\x{ffff}0-9]-*)*[a-z\x{00a1}-\x{ffff}0-9]+)*(?:\.(?:[a-z\x{00a1}-\x{ffff}]{2,}))\.?)(?::\d{2,5})?(?:[/?#]\S*)?$%uiS', $url)) { 
				return null; 
		}
		return $uri;
	}
	private function contentTypeFromHeader($headers) {
		if (preg_match('/^\s*Content-Type:\s*([^\s;\n]+)/im', $headers, $matches)) {
			return $matches[1];
		}
		return null;
	}
	private function fetchURLResult($url) {
		$result = new URLRedirectResult($url);
		
		# Attempt to fetch the headers for this URL
		list($headers, $body) = $this->fetchURL($url);
		if ( ! isset($headers)) {
			$result->didConnectionFail(true, 'Could not retrieve headers');
			return $result;
		}

		# Parse the headers...
		if (preg_match('/^\s*HTTP\/[\d\.]+\s+(\d+)/i', $headers, $matches)) {
			$result->setHTTPStatusCode($matches[1]);
		}
		else {
			$result->didFatalErrorOccur(true, 'HTTP status code not found');
		}

		$result->setContentType($this->contentTypeFromHeader($headers));

		if (preg_match('/^\s*Content-Length:\s*(\d+)/im', $headers, $matches)) {
			$result->setContentLength($matches[1]);
		}

		if ($result->hasRedirectHTTPStatus()) {
			# Parse the location header to determine the redirect URL...
			if (preg_match('/^\s*Location:\s*([^\r\n]+)/im', $headers, $matches)) {
				$result->setRedirectTarget($this->fullyQualifyURI($matches[1], $url));
			}

			# A redirect status code with no location header is a fatal error...
			else { $result->didFatalErrorOccur(true, 'HTTP status code indicated redirect, no location found'); }

			return $result;
		} else if ($result->hasSuccessHTTPStatus()) {
			return $result;
		}

		# Link had some other status code besides redirect or status...
		return $result;
	}
	private function initCurl() {
		# If curl has already been initialized (and not closed), just return the handle
		if (isset($this->curl)) { return $this->curl; }

		$this->curl = curl_init();

		curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($this->curl, CURLOPT_TIMEOUT, $this->request_timeout);

		if (isset($this->cookie_jar)) {
			curl_setopt($this->curl, CURLOPT_COOKIEJAR, $this->cookie_jar);
			curl_setopt($this->curl, CURLOPT_COOKIEFILE, $this->cookie_jar);
		}

		curl_setopt($this->curl, CURLOPT_FOLLOWLOCATION, $this->is_multiple);

		# If a User Agent has been set, set the curl option
		if (isset($this->user_agent)) {
			curl_setopt($this->curl, CURLOPT_USERAGENT, $this->user_agent);
		}

		# We are not too concerned about the strictness of SSL when finding redirects
		# Without these, some SSL links just fail to return anything
		curl_setopt($this->curl, CURLOPT_SSL_VERIFYHOST, 0); 
		curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, 0); 

		# We want the headers returned to us to follow redirects
		curl_setopt($this->curl, CURLOPT_HEADER, true); 
		$this->code = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
		return $this->curl;
	}
	private function closeCurl() {
		if (isset($this->curl)) {
			curl_close($this->curl);
			unset($this->curl);
		}

		# Empty the cookie jar (this deletes the file)
		if (isset($this->cookie_jar) && file_exists($this->cookie_jar)) {
			unlink($this->cookie_jar);
		}
	}
}
class URLRedirectResult {
	private $url;
	private $all_urls;
	private $status;
	private $arr_status;
	private $content_type;
	private $content_length;

	private $is_starting_point = false;
	private $is_open_graph = false;
	private $is_canonical = false;
	private $is_looped = false;

	private $redirect;
	private $redirect_is_open_graph = false;
	private $redirect_is_canonical = false;

	private $failed = false;
	private $error = false;
	private $error_message = '';

	public function __construct($url) { $this->url = $url; }

	# This is the best resolved URL we could obtain after following redirects.
	public function getURL() { return $this->url; }

	# All URLs
	public function getAllURL() { return $this->all_urls; }
	public function addURLS($urls) { $this->all_urls = $urls; }

	# Returns the integer [HTTP status code] for the resolved URL.
	# Examples: 200: OK (success), 404: Not Found, 301: Moved Permanently, ...
	public function getHTTPStatusCode() { return $this->status; }
	public function setHTTPStatusCode($status) { $this->status = $status; }

	public function getHTTPStatusCodes() { return $this->arr_status; }
	public function addHTTPStatusCodes($status) { $this->arr_status = $status; }

	public function getConutRedirects() { $this->count_redirects; }

	# Returns _true_ if the [HTTP status code] for the resolved URL is 200.
	public function hasSuccessHTTPStatus() { return ($this->status == 200); }

	# Returns _true_ if the [HTTP status code] for the resolved URL is 301 or 302.
	public function hasRedirectHTTPStatus() { return ($this->status == 301 || $this->status == 302 || $this->status == 303); }

	# Returns the value of the Content-Type [HTTP header] for the resolved URL.
	# If header not provided, _null_ is returned. Examples: text/html, image/jpeg, ...
	public function getContentType() { return $this->content_type; }
	public function setContentType($type) { $this->content_type = $type; }
	public function hasHTMLContentType($type=null) {
		if (!isset($type)) { $type = $this->content_type; }
		return (stripos($type, 'html') !== false);
	}

	# Returns the size of the fetched URL in bytes for the resolved URL.
	# Determined only by the Content-Length [HTTP header]. _null_ returned otherwise.
	public function getContentLength() { return $this->content_length; }
	public function setContentLength($length) { $this->content_length = $length; }

	# Returns true if resolved URL was marked as the Open Graph URL (og:url)
	public function isOpenGraphURL($value=null) {
		if (isset($value)) { $this->is_open_graph = $value ? true : false; }
		return $this->is_open_graph;
	}

	# Returns true if resolved URL was marked as the Canonical URL (rel=canonical)
	public function isCanonicalURL($value=null) {
		if (isset($value)) { $this->is_canonical = $value ? true : false; }
		return $this->is_canonical;
	}

	# Returns true if resolved URL was also the URL you passed to resolveURL().
	public function isStartingURL($value=null) {
		if (isset($value)) { $this->is_starting_point = $value ? true : false; }
		return $this->is_starting_point;
	}

	# Return true if redirect has looped
	public function isLooped() { return $this->is_looped; }
	public function hasLooped($value='') { 
		if (isset($value)) 
			$this->is_looped = $value ? true : false; 
	}

	# Returns true if an error occurred while resolving the URL.
	# If this returns false, $url_result is guaranteed to have a status code.
	public function didErrorOccur() {
		return ($this->error || $this->failed);
	}

	# Returns an explanation of what went wrong if didErrorOccur() returns true.
	public function getErrorMessageString() {
		return ($this->error || $this->failed) ? $this->error_message : '';
	}

	# Returns _true_ if there was a connection error (no header or no body returned).
	# May indicate a situation where you are more likely to try at least once more.
	# If this returns _true_, didErrorOccur() will true as well.
	public function didConnectionFail($value=null, $message=null) {
		if (isset($value)) {
			$this->failed = $value ? true : false;
			$this->error_message = $message;
		}
		return $this->failed;
	}

	public function didFatalErrorOccur($value=null, $message=null) {
		if (isset($value)) {
			$this->error = $value ? true : false;
			$this->error_message = $message;
		}
		return $this->error;
	}

	public function getRedirectTarget() { return $this->redirect; }
	public function setRedirectTarget($url) { $this->redirect = $url; }

	public function redirectTargetIsOpenGraphURL($value=null) {
		if (isset($value)) { $this->redirect_is_open_graph = $value ? true : false; }
		return $this->redirect_is_open_graph;
	}

	public function redirectTargetIsCanonicalURL($value=null) {
		if (isset($value)) { $this->redirect_is_canonical = $value ? true : false; }
		return $this->redirect_is_canonical;
	}

	public function debugStatus() {
		$attr = array();
		if ($this->failed || $this->error) { array_push($attr, 'ERROR'); }
		if ($this->is_open_graph) { array_push($attr, 'og:url'); }
		if ($this->is_canonical) { array_push($attr, 'rel=canonical'); }

		$status = '(' . $this->status;
		if (count($attr)) { $status .= '; ' . join(', ', $attr); }
		$status .= ')';

		return $status;
	}
}