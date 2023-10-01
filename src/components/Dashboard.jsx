import React, { useState, useEffect } from 'react';
import { useSearchParams } from 'react-router-dom';

const Dashboard = () => {
  const [searchParams] = useSearchParams();
  const userId = searchParams.get('user_id');
  const [userData, setUserData] = useState(null);
  const [currentPage, setCurrentPage] = useState(1);
  const itemsPerPage = 10; // Use constant for itemsPerPage

  useEffect(() => {
    // Fetch user data based on userId when the component mounts
    const fetchData = async () => {
      if (!userId) {
        return;
      }

      try {
        const response = await fetch(`/wp-json/user-summary/v1/user-data/${userId}`);
        if (!response.ok) {
          throw new Error('Network response was not ok');
        }
        const data = await response.json();
        setUserData(data);
      } catch (error) {
        console.error('Error fetching user data:', error);
      }
    };

    fetchData();
  }, [userId]);

  // Calculate the index range for items to display on the current page
  const indexOfLastItem = currentPage * itemsPerPage;
  const indexOfFirstItem = indexOfLastItem - itemsPerPage;
  const currentItems = userData ? userData.user_post_data.slice(indexOfFirstItem, indexOfLastItem) : [];

  // Function to change the current page
  const paginate = (pageNumber) => {
    setCurrentPage(pageNumber);
  };

  return (
    <div className="dashboard-container">
      {userData ? (
        <div>
          {/* Display user information */}
          <h2 className='user-info__title'>User Details</h2>
          <div className="user-info">
            <p className='user-info_username'><strong>Username:</strong> {userData.user_info.username}</p>
            <p><strong>Email:</strong> {userData.user_info.email}</p>
          </div>
          <div className="posted-articles">
            {/* Display posted articles */}
            <h3 className="posted-articles__title">Posted Articles ({userData.total_items})</h3>
            {currentItems.map((post, index) => (
              <div className="article" key={index}>
                <p className="summary-article__title">{post.title}</p>
                <div className="summary__tags">
                  {post.tags.map((tag, tagIndex) => (
                    <p className="summary-article__tags" key={tagIndex}>#{tag}</p>
                  ))}
                </div>
                <p className="summary_text">{post.summary}</p>
                <p>{post.tweet}</p>
              </div>
            ))}
          </div>
          {/* Pagination */}
          <div className="pagination">
            <ul>
              {Array.from({ length: Math.ceil(userData.user_post_data.length / itemsPerPage) }, (_, index) => (
                <li key={index}>
                  <button onClick={() => paginate(index + 1)}>{index + 1}</button>
                </li>
              ))}
            </ul>
          </div>
        </div>
      ) : (
        <p className="loading-message">Loading user data...</p>
      )}
    </div>
  );
};

export default Dashboard;
