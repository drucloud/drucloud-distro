<?php

/**
 * @file
 * Contains \Drupal\search_api\Query\QueryInterface.
 */

namespace Drupal\search_api\Query;

use Drupal\search_api\Index\IndexInterface;

/**
 * Represents a search query on a Search API index.
 *
 * Methods not returning something else will return the object itself, so calls
 * can be chained.
 */
interface QueryInterface {

  /**
   * Instantiates a new instance of this query class.
   *
   * @param \Drupal\search_api\Index\IndexInterface $index
   *   The index for which the query should be created.
   * @param array $options
   *   (optional) The options to set for the query.
   *
   * @return static
   *   A query object to use.
   */
  public static function create(IndexInterface $index, array $options = array());

  /**
   * Retrieves the parse modes supported by this query class.
   *
   * @return string[][]
   *   An associative array of parse modes recognized by objects of this class.
   *   The keys are the parse modes' IDs, values are associative arrays
   *   containing the following entries:
   *   - name: The translated name of the parse mode.
   *   - description: (optional) A translated text describing the parse mode.
   */
  public function parseModes();

  /**
   * Creates a new filter to use with this query object.
   *
   * @param string $conjunction
   *   The conjunction to use for the filter – either 'AND' or 'OR'.
   *
   * @return \Drupal\search_api\Query\FilterInterface
   *   A filter object that is set to use the specified conjunction.
   */
  public function createFilter($conjunction = 'AND');

  /**
   * Sets the keys to search for.
   *
   * If this method is not called on the query before execution, this will be a
   * filter-only query.
   *
   * @param string|array|null $keys
   *   A string with the search keys, in one of the formats specified by
   *   getKeys(). A passed string will be parsed according to the set parse
   *   mode. Use NULL to not use any search keys.
   *
   * @return $this
   */
  public function keys($keys = NULL);

  /**
   * Sets the fields that will be searched for the search keys.
   *
   * If this is not called, all fulltext fields will be searched.
   *
   * @param array $fields
   *   An array containing fulltext fields that should be searched.
   *
   * @return $this
   *
   * @throws \Drupal\search_api\Exception\SearchApiException
   *   If one of the fields isn't a fulltext field.
   */
  // @todo Allow calling with NULL, and maybe rename to setFulltextFields().
  public function fields(array $fields);

  /**
   * Adds a subfilter to this query's filter.
   *
   * @param \Drupal\search_api\Query\FilterInterface $filter
   *   A filter that should be added as a subfilter.
   *
   * @return $this
   */
  public function filter(FilterInterface $filter);

  /**
   * Adds a new ($field $operator $value) condition filter.
   *
   * @param string $field
   *   The field to filter on, e.g. 'title'. The special field
   *   "search_api_datasource" can be used to filter by datasource ID.
   * @param mixed $value
   *   The value the field should have (or be related to by the operator).
   * @param string $operator
   *   The operator to use for checking the constraint. The following operators
   *   are supported for primitive types: "=", "<>", "<", "<=", ">=", ">". They
   *   have the same semantics as the corresponding SQL operators.
   *   If $field is a fulltext field, $operator can only be "=" or "<>", which
   *   are in this case interpreted as "contains" or "doesn't contain",
   *   respectively.
   *   If $value is NULL, $operator also can only be "=" or "<>", meaning the
   *   field must have no or some value, respectively.
   *
   * @return $this
   */
  public function condition($field, $value, $operator = '=');

  /**
   * Adds a sort directive to this search query.
   *
   * If no sort is manually set, the results will be sorted descending by
   * relevance.
   *
   * @param string $field
   *   The field to sort by. The special fields 'search_api_relevance' (sort by
   *   relevance) and 'search_api_id' (sort by item id) may be used.
   * @param string $order
   *   The order to sort items in – either 'ASC' or 'DESC'.
   *
   * @return $this
   *
   * @throws \Drupal\search_api\Exception\SearchApiException
   *   If the field is multi-valued or of a fulltext type.
   */
  public function sort($field, $order = 'ASC');

  /**
   * Adds a range of results to return.
   *
   * This will be saved in the query's options. If called without parameters,
   * this will remove all range restrictions previously set.
   *
   * @param int|null $offset
   *   The zero-based offset of the first result returned.
   * @param int|null $limit
   *   The number of results to return.
   *
   * @return $this
   */
  public function range($offset = NULL, $limit = NULL);

  /**
   * Executes this search query.
   *
   * @return \Drupal\search_api\Query\ResultSetInterface
   *   The results of the search.
   *
   * @throws \Drupal\search_api\Exception\SearchApiException
   *   If an error occurred during the search.
   */
  public function execute();

  /**
   * Prepares the query object for the search.
   *
   * This method should always be called by execute() and contain all necessary
   * operations before the query is passed to the server's search() method.
   *
   * @throws \Drupal\search_api\Exception\SearchApiException
   *   If any wrong options were set on the query (e.g., conditions or sorts on
   *   unknown fields).
   */
  public function preExecute();

  /**
   * Postprocesses the search results before they are returned.
   *
   * This method should always be called by execute() and contain all necessary
   * operations after the results are returned from the server.
   *
   * @param \Drupal\search_api\Query\ResultSetInterface $results
   *   The search results returned by the server.
   */
  public function postExecute(ResultSetInterface $results);

  /**
   * Retrieves the index associated with this search.
   *
   * @return \Drupal\search_api\Index\IndexInterface
   *   The search index this query should be executed on.
   */
  public function getIndex();

  /**
   * Retrieves the search keys for this query.
   *
   * @return array|string|null
   *   This object's search keys – either a string or an array specifying a
   *   complex search expression.
   *   An array will contain a '#conjunction' key specifying the conjunction
   *   type, and search strings or nested expression arrays at numeric keys.
   *   Additionally, a '#negation' key might be present, which means – unless it
   *   maps to a FALSE value – that the search keys contained in that array
   *   should be negated, i.e. not be present in returned results. The negation
   *   works on the whole array, not on each contained term individually – i.e.,
   *   with the "AND" conjunction and negation, only results that contain all
   *   the terms in the array should be excluded; with the "OR" conjunction and
   *   negation, all results containing one or more of the terms in the array
   *   should be excluded.
   *
   * @see keys()
   */
  public function &getKeys();

  /**
   * Retrieves the unparsed search keys for this query as originally entered.
   *
   * @return array|string|null
   *   The unprocessed search keys, exactly as passed to this object. Has the
   *   same format as the return value of QueryInterface::getKeys().
   *
   * @see keys()
   */
  public function getOriginalKeys();

  /**
   * Retrieves the fulltext fields that will be searched for the search keys.
   *
   * @return string[]
   *   An array containing the fields that should be searched for the search
   *   keys.
   *
   * @see fields()
   */
  public function &getFields();

  /**
   * Retrieves the filter object associated with this search query.
   *
   * @return \Drupal\search_api\Query\FilterInterface
   *   This object's associated filter object.
   */
  public function getFilter();

  /**
   * Retrieves the sorts set for this query.
   *
   * @return string[]
   *   An array specifying the sort order for this query. Array keys are the
   *   field IDs in order of importance, the values are the respective order in
   *   which to sort the results according to the field.
   *
   * @see sort()
   */
  public function &getSorts();

  /**
   * Retrieves an option set on this search query.
   *
   * @param string $name
   *   The name of the option.
   * @param mixed $default
   *   (optional) The value to return if the specified option is not set.
   *
   * @return mixed
   *   The value of the option with the specified name, if set. $default
   *   otherwise.
   */
  public function getOption($name, $default = NULL);

  /**
   * Sets an option for this search query.
   *
   * @param string $name
   *   The name of an option. The following options are recognized by default:
   *   - conjunction: The type of conjunction to use for this query – either
   *     'AND' or 'OR'. 'AND' by default. This only influences the search keys,
   *     filters will always use AND by default.
   *   - 'parse mode': The mode with which to parse the $keys variable, if it
   *     is set and not already an array. See DefaultQuery::parseModes() for
   *     recognized parse modes.
   *   - offset: The position of the first returned search results relative to
   *     the whole result in the index.
   *   - limit: The maximum number of search results to return. -1 means no
   *     limit.
   *   - 'filter class': Can be used to change the FilterInterface
   *     implementation to use.
   *   - 'search id': A string that will be used as the identifier when storing
   *     this search in the Search API's static cache.
   *   - 'skip result count': If present and set to TRUE, the search's result
   *     count will not be needed. Service classes can check for this option to
   *     possibly avoid executing expensive operations to compute the result
   *     count in cases where it is not needed.
   *   - search_api_access_account: The account which will be used for entity
   *     access checks, if available and enabled for the index.
   *   - search_api_bypass_access: If set to TRUE, entity access checks will be
   *     skipped, even if enabled for the index.
   *   However, contrib modules might introduce arbitrary other keys with their
   *   own, special meaning. (Usually they should be prefixed with the module
   *   name, though, to avoid conflicts.)
   * @param mixed $value
   *   The new value of the option.
   *
   * @return mixed
   *   The option's previous value, or NULL if none was set.
   */
  public function setOption($name, $value);

  /**
   * Retrieves all options set for this search query.
   *
   * The return value is a reference to the options so they can also be altered
   * this way.
   *
   * @return array
   *   An associative array of query options.
   */
  public function &getOptions();

}
