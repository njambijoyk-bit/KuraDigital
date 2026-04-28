import React, { useEffect, useState } from 'react';
import useSiteStore from '../stores/useSiteStore';
import useContentStore from '../stores/useContentStore';

const defaultProjects = [
    { id: 1, title: 'Borehole Construction — Ward A', status: 'completed', category: 'Water', description: 'Drilled and equipped a borehole serving 2,000 families.', image_url: null, impact: '2,000 families' },
    { id: 2, title: 'School Renovation Program', status: 'completed', category: 'Education', description: 'Renovated 5 primary schools including new classrooms and latrines.', image_url: null, impact: '1,500 students' },
    { id: 3, title: 'Road Improvement — Market Road', status: 'ongoing', category: 'Infrastructure', description: 'Grading and gravelling of 10km market access road.', image_url: null, impact: '5,000 residents' },
    { id: 4, title: 'Youth Skills Centre', status: 'planned', category: 'Youth', description: 'Construction of a modern youth training centre with ICT equipment.', image_url: null, impact: '500 youth annually' },
];

const statusColors = {
    completed: 'bg-green-100 text-green-700',
    ongoing: 'bg-blue-100 text-blue-700',
    planned: 'bg-yellow-100 text-yellow-700',
};

export default function ProjectsPage() {
    const { site } = useSiteStore();
    const { projects, fetchProjects } = useContentStore();
    const [filter, setFilter] = useState('All');

    useEffect(() => {
        if (site?.id) fetchProjects(site.id);
    }, [site?.id, fetchProjects]);

    const displayProjects = projects.length > 0 ? projects : defaultProjects;
    const categories = ['All', ...new Set(displayProjects.map((p) => p.category).filter(Boolean))];
    const filtered = filter === 'All' ? displayProjects : displayProjects.filter((p) => p.category === filter);

    return (
        <div>
            <section className="bg-dark-900 text-white py-20">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                    <h1 className="text-4xl md:text-5xl font-heading font-bold mb-4">Projects & Track Record</h1>
                    <p className="text-lg text-gray-300 max-w-2xl mx-auto">
                        Real results, real impact. See the projects that are transforming {site?.constituency || 'our constituency'}.
                    </p>
                </div>
            </section>

            <section className="py-16 bg-gray-50">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex flex-wrap gap-2 justify-center mb-10">
                        {categories.map((cat) => (
                            <button
                                key={cat}
                                onClick={() => setFilter(cat)}
                                className={`px-4 py-2 rounded-full text-sm font-medium transition-colors ${
                                    filter === cat
                                        ? 'bg-primary-600 text-white'
                                        : 'bg-white text-gray-700 hover:bg-gray-100 border'
                                }`}
                            >
                                {cat}
                            </button>
                        ))}
                    </div>

                    <div className="grid md:grid-cols-2 gap-8">
                        {filtered.map((project) => (
                            <div key={project.id} className="card flex flex-col sm:flex-row">
                                <div className="sm:w-48 flex-shrink-0">
                                    {project.image_url ? (
                                        <img src={project.image_url} alt={project.title} className="w-full h-full object-cover" />
                                    ) : (
                                        <div className="w-full h-48 sm:h-full bg-gradient-to-br from-primary-100 to-primary-200 flex items-center justify-center">
                                            <span className="text-4xl">🏗️</span>
                                        </div>
                                    )}
                                </div>
                                <div className="p-5 flex-1">
                                    <div className="flex items-center gap-2 mb-2">
                                        <span className={`px-2 py-0.5 text-xs font-semibold rounded-full ${statusColors[project.status] || 'bg-gray-100 text-gray-600'}`}>
                                            {project.status}
                                        </span>
                                        {project.category && (
                                            <span className="text-xs text-gray-500">{project.category}</span>
                                        )}
                                    </div>
                                    <h3 className="font-heading font-bold text-lg mb-2">{project.title}</h3>
                                    <p className="text-gray-600 text-sm mb-3">{project.description}</p>
                                    {project.impact && (
                                        <p className="text-primary-600 text-sm font-semibold">Impact: {project.impact}</p>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </section>
        </div>
    );
}
