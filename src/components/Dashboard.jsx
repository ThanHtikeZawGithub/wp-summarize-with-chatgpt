import React, { useState, useEffect } from 'react';
import { useSearchParams } from 'react-router-dom';

const Dashboard = () => {
    const [userData, setUserData] = useState(null);
    const [searchParams] = useSearchParams();
    const userId = searchParams.get('user_id');

    useEffect(() => {
        // Fetch user data based on the userId
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

    return (
        <div className="dashboard-container">
            {userData ? (
                <div>
                    <h2 className='user-info__title'>User Details</h2>
                    <div className="user-info">
                        <p className='user-info_username'><strong>Username:</strong> {userData.user_info.username}</p>
                        <p><strong>Email:</strong> {userData.user_info.email}</p>
                    </div>
                    <div className="posted-articles">
                        <h3 className='posted-articles__title'>Posted Articles</h3>
                        {userData.user_post_data.map((posts, index) => (
                            <div className="article" key={index}>
                                <p className='summary-article__title'>{posts.title}</p>
                                <div className='summary__tags'>
                                {posts.tags.map((tag) => (
                                    <p className='summary-article__tags'>#{tag}</p>
                                ))}
                                </div>
                                <p className='summary_text'>{posts.summary}</p>
                                <p>{posts.tweet}</p>
                            </div>
                        ))}
                    </div>
                </div>
            ) : (
                <p className="loading-message">Loading user data...</p>
            )}
        </div>
    );
}

export default Dashboard;
